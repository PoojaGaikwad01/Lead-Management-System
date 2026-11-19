<?php

namespace App\Modules\Leads;

use App\Http\Controllers\Controller;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadTypeController extends Controller
{
    public const COLORS = ['pink', 'green', 'purple', 'blue', 'slate', 'amber', 'gray', 'orange', 'red'];

    public const FIELD_TYPES = ['text', 'number', 'date', 'select'];

    public function index(): JsonResponse
    {
        return response()->json(['data' => LeadType::with('fields')->withCount('leads')->orderBy('sort')->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $data = $this->validated($request);

        $slug = Str::slug($data['name']) ?: 'type';
        $base = $slug;
        for ($i = 2; LeadType::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        $type = DB::transaction(function () use ($data, $slug) {
            $type = LeadType::create([...collect($data)->except('fields')->all(), 'slug' => $slug]);
            $this->syncFields($type, $data['fields'] ?? []);

            return $type;
        });

        return response()->json(['data' => $type->load('fields')], 201);
    }

    public function update(Request $request, LeadType $leadType): JsonResponse
    {
        Gate::authorize('manage-settings');
        $data = $this->validated($request, $leadType);

        DB::transaction(function () use ($leadType, $data) {
            $leadType->update(collect($data)->except('fields')->all());
            $this->syncFields($leadType, $data['fields'] ?? []);
        });

        return response()->json(['data' => $leadType->load('fields')]);
    }

    public function destroy(LeadType $leadType): Response
    {
        Gate::authorize('manage-settings');
        if (Lead::where('lead_type_id', $leadType->id)->exists()) {
            throw ValidationException::withMessages(['lead_type' => 'This type is used by existing leads. Mark it inactive instead.']);
        }
        $leadType->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?LeadType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_types', 'name')->ignore($type)],
            'color' => ['required', Rule::in(self::COLORS)],
            'sort' => ['integer', 'min:0', 'max:999'],
            'active' => ['boolean'],
            'keywords' => ['present', 'array'],
            'keywords.*' => ['string', 'max:60'],
            'fields' => ['present', 'array', 'max:20'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:100'],
            'fields.*.field_type' => ['required', Rule::in(self::FIELD_TYPES)],
            'fields.*.options' => ['nullable', 'array', 'required_if:fields.*.field_type,select'],
            'fields.*.options.*' => ['string', 'max:100'],
            'fields.*.required' => ['boolean'],
        ], [
            'fields.*.key.regex' => 'Field keys use lowercase letters, numbers and underscores, starting with a letter.',
            'fields.*.key.distinct' => 'Each field key must be unique within the type.',
        ]);

        $data['keywords'] = array_values(array_unique(array_filter(array_map(fn ($k) => Str::lower(trim($k)), $data['keywords']))));

        return $data;
    }

    /** Keeps existing fields (their key never changes, so stored values stay linked), adds new ones, removes the rest. */
    private function syncFields(LeadType $type, array $fields): void
    {
        $existing = $type->fields()->get()->keyBy('id');
        $kept = [];

        foreach (array_values($fields) as $sort => $field) {
            $attributes = [
                'label' => $field['label'],
                'field_type' => $field['field_type'],
                'options' => $field['field_type'] === 'select' ? array_values(array_filter($field['options'] ?? [])) : null,
                'required' => (bool) ($field['required'] ?? false),
                'sort' => $sort,
            ];

            $match = $existing->get($field['id'] ?? 0) ?? $existing->firstWhere('key', $field['key']);
            if ($match) {
                $match->update($attributes);
                $kept[] = $match->id;
            } else {
                $kept[] = $type->fields()->create([...$attributes, 'key' => $field['key']])->id;
            }
        }

        $type->fields()->whereNotIn('id', $kept)->delete();
    }
}
