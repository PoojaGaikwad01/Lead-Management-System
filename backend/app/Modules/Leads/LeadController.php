<?php

namespace App\Modules\Leads;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Enums\LeadStatus;
use App\Modules\Leads\Enums\ReplyStatus;
use App\Modules\Leads\Models\ActivityLog;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['new', 'reply_not_sent', 'mine', 'all'])],
            'q' => ['nullable', 'string', 'max:100'],
            'lead_type_id' => ['nullable', 'integer'],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'branch_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'regex:/^(none|\d+)$/'],
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'reply_status' => ['nullable', Rule::enum(ReplyStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Lead::visibleTo($request->user())->with(['contact', 'leadType', 'branch', 'assignee', 'latestMessage']);
        $this->applyFilters($query, $filters, $request->user());

        return LeadResource::collection(
            $query->orderByRaw('COALESCE(last_message_at, created_at) DESC')->orderByDesc('id')->paginate(30)
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $base = fn () => Lead::visibleTo($user);
        $tz = config('lms.timezone');
        $replyNotSent = $base()->where('reply_status', ReplyStatus::NotSent)->count();

        return response()->json(['data' => [
            'tabs' => [
                'new' => $base()->where('status', LeadStatus::New)->count(),
                'reply_not_sent' => $replyNotSent,
                'mine' => $base()->where('assigned_to', $user->id)->count(),
                'all' => $base()->count(),
            ],
            'cards' => [
                'new_today' => $base()->where('created_at', '>=', now($tz)->startOfDay()->utc())->count(),
                'unassigned' => $base()->open()->whereNull('assigned_to')->count(),
                'reply_not_sent' => $replyNotSent,
                'converted_this_week' => $base()->where('status', LeadStatus::Converted)
                    ->where('converted_at', '>=', now($tz)->startOfWeek()->utc())->count(),
            ],
            'unread' => $base()->where('unread_count', '>', 0)->count(),
        ]]);
    }

    public function show(Lead $lead): LeadResource
    {
        Gate::authorize('view', $lead);

        return new LeadResource($lead->load(['contact', 'leadType', 'branch', 'assignee']));
    }

    public function update(Request $request, Lead $lead): LeadResource
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lead_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('lead_types', 'id')],
            'branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')],
            'custom_data' => ['sometimes', 'nullable', 'array'],
        ]);

        if (array_key_exists('branch_id', $data) && $data['branch_id'] !== $lead->branch_id) {
            abort_unless($request->user()->hasAdminAccess(), 403, 'Only admins can change the branch.');
        }

        $before = ['name' => $lead->contact->name, 'lead_type_id' => $lead->lead_type_id, 'branch_id' => $lead->branch_id, 'custom_data' => $lead->custom_data ?? []];

        if (array_key_exists('lead_type_id', $data) || array_key_exists('custom_data', $data)) {
            $typeId = array_key_exists('lead_type_id', $data) ? $data['lead_type_id'] : $lead->lead_type_id;
            $type = $typeId ? LeadType::with('fields')->find($typeId) : null;
            $lead->lead_type_id = $typeId;
            $lead->custom_data = $this->customData($type, $data['custom_data'] ?? $lead->custom_data ?? []) ?: null;
        }
        if (array_key_exists('branch_id', $data)) {
            $lead->branch_id = $data['branch_id'];
        }
        if (array_key_exists('name', $data)) {
            $lead->contact->update(['name' => $data['name']]);
        }
        $lead->save();

        $after = ['name' => $lead->contact->name, 'lead_type_id' => $lead->lead_type_id, 'branch_id' => $lead->branch_id, 'custom_data' => $lead->custom_data ?? []];
        $changed = array_keys(array_filter($after, fn ($value, $key) => $value != $before[$key], ARRAY_FILTER_USE_BOTH));
        if ($changed) {
            ActivityLog::record($lead, $request->user(), 'updated', ['fields' => $changed]);
        }

        return new LeadResource($lead->load(['contact', 'leadType', 'branch', 'assignee']));
    }

    private function customData(?LeadType $type, array $values): array
    {
        if (! $type || $type->fields->isEmpty()) {
            return [];
        }

        $rules = [];
        $names = [];
        foreach ($type->fields as $field) {
            $rules["custom_data.{$field->key}"] = [
                $field->required ? 'required' : 'nullable',
                ...match ($field->field_type) {
                    'number' => ['numeric'],
                    'date' => ['date'],
                    'select' => [Rule::in($field->options ?? [])],
                    default => ['string', 'max:1000'],
                },
            ];
            $names["custom_data.{$field->key}"] = $field->label;
        }

        $valid = Validator::make(['custom_data' => $values], $rules, [], $names)->validate()['custom_data'] ?? [];

        return array_filter($valid, fn ($value) => $value !== null && $value !== '');
    }

    public function markRead(Lead $lead): Response
    {
        Gate::authorize('view', $lead);

        $lead->messages()->where('direction', 'in')->whereNull('read_at')->update(['read_at' => now()]);
        if ($lead->unread_count) {
            $lead->update(['unread_count' => 0]);
        }

        return response()->noContent();
    }

    private function applyFilters(Builder $query, array $filters, User $user): void
    {
        match ($filters['tab'] ?? 'all') {
            'new' => $query->where('status', LeadStatus::New),
            'reply_not_sent' => $query->where('reply_status', ReplyStatus::NotSent),
            'mine' => $query->where('assigned_to', $user->id),
            default => null,
        };

        if ($search = trim($filters['q'] ?? '')) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $digits = preg_replace('/\D/', '', $search);
            $query->whereHas('contact', function (Builder $contact) use ($like, $digits) {
                $contact->where('name', 'like', $like);
                if (strlen($digits) >= 3) {
                    $contact->orWhere('phone', 'like', "%{$digits}%");
                }
            });
        }

        foreach (['lead_type_id', 'source', 'branch_id', 'status', 'reply_status'] as $key) {
            if (filled($filters[$key] ?? null)) {
                $query->where($key, $filters[$key]);
            }
        }

        if (filled($filters['assigned_to'] ?? null)) {
            $filters['assigned_to'] === 'none'
                ? $query->whereNull('assigned_to')
                : $query->where('assigned_to', $filters['assigned_to']);
        }

        $tz = config('lms.timezone');
        if (filled($filters['from'] ?? null)) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'], $tz)->startOfDay()->utc());
        }
        if (filled($filters['to'] ?? null)) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'], $tz)->endOfDay()->utc());
        }
    }
}
