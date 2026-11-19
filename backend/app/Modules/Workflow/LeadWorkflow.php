<?php

namespace App\Modules\Workflow;

use App\Models\User;
use App\Modules\Auth\Role;
use App\Modules\Branches\Branch;
use App\Modules\Integration\PortalLeadResource;
use App\Modules\Integration\SendWebhook;
use App\Modules\Integration\WebhookSubscription;
use App\Modules\Leads\Enums\LeadStatus;
use App\Modules\Leads\Enums\ReplyStatus;
use App\Modules\Leads\Models\ActivityLog;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadNote;
use App\Modules\Leads\Models\Reminder;
use App\Modules\Settings\Setting;
use App\Modules\Workflow\Notifications\LeadAssigned;
use App\Modules\Workflow\Notifications\ReplyReminder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class LeadWorkflow
{
    public function __construct(private StaffAlert $alerts) {}

    public function assign(Lead $lead, User $by, ?User $assignee, ?Branch $branch): Lead
    {
        $previous = $lead->assigned_to;

        DB::transaction(function () use ($lead, $by, $assignee, $branch) {
            $lead->fill([
                'assigned_to' => $assignee?->id,
                'branch_id' => $branch?->id,
                'assigned_by' => $by->id,
                'assigned_at' => now(),
                'reply_status' => ReplyStatus::NotSent,
                'reply_sent_at' => null,
                'reply_sent_by' => null,
                'reminder_count' => 0,
                'last_reminder_at' => null,
            ]);
            if ($lead->status === LeadStatus::New) {
                $lead->status = LeadStatus::Assigned;
            }
            $lead->save();
            ActivityLog::record($lead, $by, 'assigned', ['to_user' => $assignee?->name, 'to_branch' => $branch?->name]);
        });

        $lead->load('contact', 'leadType', 'branch');
        $recipients = $this->recipients($lead)->reject(fn (User $user) => $user->is($by));
        Notification::send($recipients, new LeadAssigned($lead));
        $this->alerts->send($recipients, "Funky Monkey Leads: new lead assigned - {$this->who($lead)}. Please reply soon.");
        LeadEvent::send($lead, array_filter([$previous]));

        return $lead;
    }

    public function markReplySent(Lead $lead, User $by, ?string $note): Lead
    {
        if ($lead->reply_status !== ReplyStatus::NotSent) {
            throw ValidationException::withMessages(['reply' => 'No reply is pending for this lead.']);
        }

        DB::transaction(function () use ($lead, $by, $note) {
            $lead->fill(['reply_status' => ReplyStatus::Sent, 'reply_sent_at' => now(), 'reply_sent_by' => $by->id]);
            if (in_array($lead->status, [LeadStatus::New, LeadStatus::Assigned], true)) {
                $lead->status = LeadStatus::Contacted;
            }
            $lead->save();
            if (filled($note)) {
                $lead->notes()->create(['user_id' => $by->id, 'kind' => 'note', 'body' => $note]);
            }
            ActivityLog::record($lead, $by, 'reply_sent');
        });

        LeadEvent::send($lead);

        return $lead;
    }

    public function changeStatus(Lead $lead, User $by, LeadStatus $status, ?string $reason, ?string $note): Lead
    {
        $from = $lead->status;

        DB::transaction(function () use ($lead, $by, $status, $reason, $note, $from) {
            $lead->status = $status;
            $lead->lost_reason = $status === LeadStatus::Lost ? $reason : null;
            $lead->converted_at = $status === LeadStatus::Converted ? ($lead->converted_at ?? now()) : null;
            $lead->closed_at = in_array($status, [LeadStatus::Lost, LeadStatus::Closed], true) ? ($lead->closed_at ?? now()) : null;
            $lead->save();
            if (filled($note)) {
                $lead->notes()->create(['user_id' => $by->id, 'kind' => 'note', 'body' => $note]);
            }
            ActivityLog::record($lead, $by, 'status_changed', ['from' => $from->value, 'to' => $status->value, 'reason' => $lead->lost_reason]);
        });

        if ($status === LeadStatus::Converted && $from !== LeadStatus::Converted) {
            $payload = (new PortalLeadResource($lead->load(['contact', 'leadType', 'branch', 'assignee'])))->resolve();
            WebhookSubscription::where('active', true)->get()
                ->filter(fn (WebhookSubscription $w) => in_array('lead.converted', $w->events ?? [], true))
                ->each(fn (WebhookSubscription $w) => SendWebhook::dispatch($w->id, 'lead.converted', $payload));
        }

        LeadEvent::send($lead);

        return $lead;
    }

    public function remind(Lead $lead, ?User $by): Reminder
    {
        if ($lead->reply_status !== ReplyStatus::NotSent) {
            throw ValidationException::withMessages(['reminder' => 'The reply has already been sent.']);
        }
        $lead->loadMissing('contact');
        $recipients = $this->recipients($lead);
        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages(['reminder' => 'This lead has no active assignee to remind.']);
        }

        $whatsapp = $this->alerts->send($recipients, "Funky Monkey Leads reminder: please reply to {$this->who($lead)} and mark the reply as sent.");

        $reminder = DB::transaction(function () use ($lead, $by, $recipients, $whatsapp) {
            $reminder = $lead->reminders()->create([
                'sent_by' => $by?->id,
                'to_user_id' => $lead->assigned_to,
                'channel' => $whatsapp ? 'in_app+whatsapp' : 'in_app',
                'auto' => $by === null,
            ]);
            $lead->forceFill(['reminder_count' => $lead->reminder_count + 1, 'last_reminder_at' => now()])->save();
            ActivityLog::record($lead, $by, 'reminder_sent', [
                'to' => $recipients->pluck('name')->join(', '),
                'auto' => $by === null,
            ]);

            return $reminder;
        });

        Notification::send($recipients, new ReplyReminder($lead, $by === null));
        LeadEvent::send($lead);

        return $reminder;
    }

    /** Sends automatic reminders when enabled: one per `after_hours` window while the reply is pending. */
    public function sendDueReminders(): int
    {
        $rules = Setting::reminderRules();
        if (! $rules['auto_enabled']) {
            return 0;
        }

        $cutoff = now()->subHours((int) $rules['after_hours']);
        $leads = Lead::open()
            ->where('reply_status', ReplyStatus::NotSent)
            ->where('assigned_at', '<=', $cutoff)
            ->where(fn ($q) => $q->whereNull('last_reminder_at')->orWhere('last_reminder_at', '<=', $cutoff))
            ->get();

        $sent = 0;
        foreach ($leads as $lead) {
            try {
                $this->remind($lead, null);
                $sent++;
            } catch (ValidationException) {
            }
        }

        return $sent;
    }

    public function addNote(Lead $lead, User $by, string $kind, ?string $body, ?string $outcome): LeadNote
    {
        $note = $lead->notes()->create(['user_id' => $by->id, 'kind' => $kind, 'body' => $body, 'call_outcome' => $kind === 'call' ? $outcome : null]);
        ActivityLog::record($lead, $by, $kind === 'call' ? 'call_logged' : 'note_added', $kind === 'call' ? ['outcome' => $outcome] : []);
        LeadEvent::send($lead);

        return $note;
    }

    /** The assignee, or active branch staff when a lead is assigned to a branch only. */
    public function recipients(Lead $lead): Collection
    {
        if ($lead->assigned_to) {
            return User::whereKey($lead->assigned_to)->where('active', true)->get();
        }
        if ($lead->branch_id) {
            return User::where('branch_id', $lead->branch_id)->where('role', Role::Assignee)->where('active', true)->get();
        }

        return collect();
    }

    private function who(Lead $lead): string
    {
        return trim(($lead->contact->name ? $lead->contact->name.' ' : '').'('.$lead->contact->phone.')');
    }
}
