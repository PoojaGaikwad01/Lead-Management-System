<?php

namespace App\Modules\Workflow;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Branches\Branch;
use App\Modules\Leads\Enums\LeadStatus;
use App\Modules\Leads\LeadResource;
use App\Modules\Leads\Models\ActivityLog;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadNote;
use App\Modules\Leads\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function __construct(private LeadWorkflow $workflow) {}

    public function assign(Request $request, Lead $lead): LeadResource
    {
        Gate::authorize('assign', $lead);
        $data = $request->validate([
            'assigned_to' => ['nullable', 'required_without:branch_id', 'integer', Rule::exists('users', 'id')->where('active', true)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
        ], ['assigned_to.required_without' => 'Choose a person or a branch.']);

        $this->workflow->assign(
            $lead,
            $request->user(),
            isset($data['assigned_to']) ? User::find($data['assigned_to']) : null,
            isset($data['branch_id']) ? Branch::find($data['branch_id']) : null,
        );

        return $this->fresh($lead);
    }

    public function replySent(Request $request, Lead $lead): LeadResource
    {
        Gate::authorize('markReplySent', $lead);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->markReplySent($lead, $request->user(), $data['note'] ?? null);

        return $this->fresh($lead);
    }

    public function status(Request $request, Lead $lead): LeadResource
    {
        Gate::authorize('update', $lead);
        $data = $request->validate([
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['lost_reason.required_if' => 'Give a reason when marking a lead as lost.']);

        $this->workflow->changeStatus($lead, $request->user(), LeadStatus::from($data['status']), $data['lost_reason'] ?? null, $data['note'] ?? null);

        return $this->fresh($lead);
    }

    public function remind(Request $request, Lead $lead): LeadResource
    {
        Gate::authorize('remind', $lead);

        $this->workflow->remind($lead, $request->user());

        return $this->fresh($lead);
    }

    public function storeNote(Request $request, Lead $lead): JsonResponse
    {
        Gate::authorize('update', $lead);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['note', 'call'])],
            'body' => ['nullable', 'required_if:kind,note', 'string', 'max:2000'],
            'call_outcome' => ['nullable', 'required_if:kind,call', Rule::in(LeadNote::CALL_OUTCOMES)],
        ]);

        $this->workflow->addNote($lead, $request->user(), $data['kind'], $data['body'] ?? null, $data['call_outcome'] ?? null);

        return $this->timeline($lead)->setStatusCode(201);
    }

    public function timeline(Lead $lead): JsonResponse
    {
        Gate::authorize('view', $lead);

        return response()->json(['data' => [
            'notes' => $lead->notes()->with('user')->latest('id')->get()->map(fn (LeadNote $n) => [
                'id' => $n->id,
                'kind' => $n->kind,
                'body' => $n->body,
                'call_outcome' => $n->call_outcome,
                'user' => $n->user?->name,
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'reminders' => $lead->reminders()->with('sender', 'recipient')->latest('id')->get()->map(fn (Reminder $r) => [
                'id' => $r->id,
                'auto' => $r->auto,
                'channel' => $r->channel,
                'sender' => $r->sender?->name,
                'recipient' => $r->recipient?->name,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
            'activity' => $lead->activity()->with('user')->latest('id')->limit(100)->get()->map(fn (ActivityLog $a) => [
                'id' => $a->id,
                'text' => ActivityPresenter::text($a),
                'created_at' => $a->created_at?->toIso8601String(),
            ]),
        ]]);
    }

    private function fresh(Lead $lead): LeadResource
    {
        return new LeadResource($lead->refresh()->load(['contact', 'leadType', 'branch', 'assignee']));
    }
}
