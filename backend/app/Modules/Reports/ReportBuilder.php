<?php

namespace App\Modules\Reports;

use App\Models\User;
use App\Modules\Branches\Branch;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Enums\LeadStatus;
use App\Modules\Leads\Enums\ReplyStatus;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadType;
use App\Modules\Leads\Models\Reminder;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** All report figures for leads created in a date range (computed in PHP so SQLite and MySQL agree). */
class ReportBuilder
{
    private const SOURCES = [
        'whatsapp_enquiry' => 'WhatsApp enquiry', 'agency_text' => 'Agency text', 'agency_file' => 'Agency file', 'manual_upload' => 'Manual upload',
    ];

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $leads = $this->leads($from, $to);
        $types = LeadType::pluck('name', 'id');
        $branches = Branch::pluck('name', 'id');
        $users = User::pluck('name', 'id');
        $reminders = Reminder::whereIn('lead_id', $leads->pluck('id'))->get(['lead_id', 'to_user_id']);

        $converted = $leads->where('status', LeadStatus::Converted)->count();

        return [
            'totals' => [
                'leads' => $leads->count(),
                'converted' => $converted,
                'lost' => $leads->where('status', LeadStatus::Lost)->count(),
                'conversion_rate' => $leads->count() ? round($converted / $leads->count() * 100, 1) : null,
                'avg_minutes_to_assign' => $this->average($leads, fn (Lead $l) => $this->minutes($l->created_at, $l->assigned_at)),
                'avg_minutes_to_reply' => $this->average($leads, fn (Lead $l) => $this->minutes($l->assigned_at, $l->reply_sent_at)),
                'reminders' => $reminders->count(),
            ],
            'by_type' => $this->breakdown($leads, 'lead_type_id', fn ($id) => $types[$id] ?? 'Deleted type', 'Not set'),
            'by_source' => $this->breakdown($leads, fn (Lead $l) => $l->source->value, fn ($key) => self::SOURCES[$key] ?? $key, 'Unknown'),
            'by_branch' => $this->breakdown($leads, 'branch_id', fn ($id) => $branches[$id] ?? 'Deleted branch', 'No branch'),
            'by_assignee' => $leads->whereNotNull('assigned_to')->groupBy('assigned_to')->map(fn (Collection $group, $userId) => [
                'label' => $users[$userId] ?? 'Deleted user',
                'total' => $group->count(),
                'replied' => $group->where('reply_status', ReplyStatus::Sent)->count(),
                'awaiting_reply' => $group->where('reply_status', ReplyStatus::NotSent)->count(),
                'converted' => $group->where('status', LeadStatus::Converted)->count(),
                'avg_minutes_to_reply' => $this->average($group, fn (Lead $l) => $this->minutes($l->assigned_at, $l->reply_sent_at)),
                'reminders' => $reminders->where('to_user_id', (int) $userId)->count(),
            ])->sortByDesc('total')->values(),
            'lost_reasons' => $leads->where('status', LeadStatus::Lost)
                ->groupBy(fn (Lead $l) => $l->lost_reason ?: 'No reason given')
                ->map(fn (Collection $group, $reason) => ['label' => $reason, 'total' => $group->count()])
                ->sortByDesc('total')->values(),
        ];
    }

    public function leads(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Lead::whereBetween('created_at', [$from, $to])->get([
            'id', 'contact_id', 'lead_type_id', 'branch_id', 'source', 'assigned_to', 'status', 'reply_status',
            'created_at', 'assigned_at', 'reply_sent_at', 'converted_at', 'reminder_count', 'lost_reason',
        ]);
    }

    public function minutes(?CarbonInterface $start, ?CarbonInterface $end): ?int
    {
        return $start && $end ? (int) round(max(0, $start->diffInSeconds($end, false)) / 60) : null;
    }

    private function average(Collection $leads, callable $value): ?int
    {
        $values = $leads->map($value)->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : (int) round($values->avg());
    }

    private function breakdown(Collection $leads, string|callable $key, callable $label, string $none): Collection
    {
        return $leads->groupBy(fn (Lead $l) => (is_callable($key) ? $key($l) : $l->{$key}) ?? '')
            ->map(fn (Collection $group, $id) => [
                'label' => $id === '' ? $none : $label($id),
                'total' => $group->count(),
                'converted' => $group->where('status', LeadStatus::Converted)->count(),
            ])
            ->sortByDesc('total')
            ->values();
    }
}
