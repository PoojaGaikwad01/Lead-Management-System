<?php

namespace App\Modules\Leads;

use App\Modules\Leads\Models\ActivityLog;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadType;

/** Fills a customer lead's empty type, branch and custom fields from message text (never overrides). */
class LeadEnricher
{
    public function __construct(private LeadClassifier $classifier, private AiExtractor $ai) {}

    public function enrich(Lead $lead, string $text): void
    {
        $detected = [];

        if (! $lead->lead_type_id && ($type = $this->classifier->type($text))) {
            $lead->lead_type_id = $type->id;
            $detected['lead_type'] = $type->name;
        }
        if (! $lead->branch_id && ($branch = $this->classifier->branch($text))) {
            $lead->branch_id = $branch->id;
            $detected['branch'] = $branch->name;
        }

        $type = $lead->lead_type_id ? LeadType::with('fields')->find($lead->lead_type_id) : null;
        $missingFields = $type?->fields->contains(fn ($f) => blank($lead->custom_data[$f->key] ?? null)) ?? false;

        if (AiExtractor::enabled() && (! $type || ! $lead->branch_id || $missingFields)) {
            $this->applyAi($lead, $text, $detected);
        }

        if ($lead->isDirty()) {
            $lead->save();
            ActivityLog::record($lead, null, 'auto_detected', $detected);
        }
    }

    private function applyAi(Lead $lead, string $text, array &$detected): void
    {
        $result = $this->ai->extract($text)[0] ?? null;
        if (! $result) {
            return;
        }

        if (! $lead->lead_type_id && ($type = $this->classifier->typeByName($result['type']))) {
            $lead->lead_type_id = $type->id;
            $detected['lead_type'] = $type->name;
        }
        if (! $lead->branch_id && ($branch = $this->classifier->branchByName($result['branch']))) {
            $lead->branch_id = $branch->id;
            $detected['branch'] = $branch->name;
        }

        $type = $lead->lead_type_id ? LeadType::with('fields')->find($lead->lead_type_id) : null;
        $fields = FieldValues::map($type, $result['fields']);
        $merged = [...$fields, ...array_filter($lead->custom_data ?? [], fn ($v) => filled($v))];
        if ($merged !== ($lead->custom_data ?? [])) {
            $lead->custom_data = $merged;
            $detected['fields'] = array_keys($fields);
        }
        $detected['ai'] = true;
    }
}
