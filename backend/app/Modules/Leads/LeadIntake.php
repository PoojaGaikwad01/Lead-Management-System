<?php

namespace App\Modules\Leads;

use App\Modules\Branches\Branch;
use App\Modules\Imports\Import;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\ActivityLog;
use App\Modules\Leads\Models\Contact;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadType;
use Illuminate\Support\Facades\DB;

/** Creates a lead for a phone number, or merges into the contact's open lead (dedupe by phone). */
class LeadIntake
{
    public function __construct(private LeadClassifier $classifier) {}

    /**
     * @param  array{name?: ?string, type?: LeadType|string|null, branch?: Branch|string|null, fields?: array, text?: ?string}  $data
     * @return array{0: 'created'|'duplicate', 1: Lead}
     */
    public function upsert(string $phone, array $data, LeadSource $source, ?Import $import = null): array
    {
        $type = $data['type'] ?? null;
        $type = $type instanceof LeadType ? $type : ($this->classifier->typeByName($type) ?? $this->classifier->type((string) ($data['text'] ?? '')));
        $branch = $data['branch'] ?? null;
        $branch = $branch instanceof Branch ? $branch : ($this->classifier->branchByName($branch) ?? $this->classifier->branch((string) ($data['text'] ?? '')));
        $type?->loadMissing('fields');
        $fields = FieldValues::map($type, $data['fields'] ?? []);
        $name = filled($data['name'] ?? null) ? trim($data['name']) : null;

        return DB::transaction(function () use ($phone, $name, $type, $branch, $fields, $source, $import, $data) {
            $contact = Contact::firstOrCreate(['phone' => $phone], ['name' => $name]);
            if (! $contact->name && $name) {
                $contact->update(['name' => $name]);
            }

            $open = Lead::where('contact_id', $contact->id)->open()->latest('id')->first();
            if ($open) {
                $open->lead_type_id ??= $type?->id;
                $open->branch_id ??= $branch?->id;
                $open->custom_data = [...$fields, ...($open->custom_data ?? [])];
                $open->save();
                ActivityLog::record($open, $import?->uploader, 'merged', ['source' => $source->value, 'import_id' => $import?->id]);

                return ['duplicate', $open];
            }

            $lead = Lead::create([
                'contact_id' => $contact->id,
                'source' => $source,
                'lead_type_id' => $type?->id,
                'branch_id' => $branch?->id,
                'custom_data' => $fields ?: null,
                'import_id' => $import?->id,
                'source_text' => filled($data['text'] ?? null) ? trim($data['text']) : null,
                'last_message_at' => null,
            ]);
            ActivityLog::record($lead, $import?->uploader, 'created', ['source' => $source->value, 'import_id' => $import?->id]);

            return ['created', $lead];
        });
    }
}
