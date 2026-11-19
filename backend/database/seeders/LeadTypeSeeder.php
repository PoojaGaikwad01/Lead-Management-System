<?php

namespace Database\Seeders;

use App\Modules\Leads\Models\LeadType;
use Illuminate\Database\Seeder;

class LeadTypeSeeder extends Seeder
{
    /** Default detection keywords per lead type (admin-editable). */
    public const KEYWORDS = [
        'birthday-party' => ['birthday', 'bday', "b'day", 'party', 'cake', 'celebration'],
        'play-zone-visit' => ['play zone', 'playzone', 'play area', 'visit', 'entry', 'ticket', 'tickets', 'timings'],
        'membership' => ['membership', 'member', 'pass', 'subscription', 'monthly', 'annual'],
        'events-school-trip' => ['school', 'trip', 'students', 'excursion', 'picnic', 'event'],
        'corporate-group' => ['corporate', 'company', 'office', 'team outing', 'offsite', 'employees', 'group booking'],
        'franchise' => ['franchise', 'investment', 'invest', 'partnership', 'business opportunity'],
        'general' => [],
    ];

    public function run(): void
    {
        $types = [
            ['slug' => 'birthday-party', 'name' => 'Birthday Party', 'color' => 'pink', 'fields' => [
                ['key' => 'party_date', 'label' => 'Party date', 'field_type' => 'date'],
                ['key' => 'kids_count', 'label' => 'Number of kids', 'field_type' => 'number'],
            ]],
            ['slug' => 'play-zone-visit', 'name' => 'Play Zone Visit', 'color' => 'green'],
            ['slug' => 'membership', 'name' => 'Membership', 'color' => 'purple'],
            ['slug' => 'events-school-trip', 'name' => 'Events/School Trip', 'color' => 'blue'],
            ['slug' => 'corporate-group', 'name' => 'Corporate/Group', 'color' => 'slate'],
            ['slug' => 'franchise', 'name' => 'Franchise', 'color' => 'amber', 'fields' => [
                ['key' => 'city', 'label' => 'City', 'field_type' => 'text'],
                ['key' => 'budget', 'label' => 'Budget', 'field_type' => 'text'],
            ]],
            ['slug' => 'general', 'name' => 'General', 'color' => 'gray'],
        ];

        foreach ($types as $sort => $data) {
            $type = LeadType::firstOrCreate(
                ['slug' => $data['slug']],
                ['name' => $data['name'], 'color' => $data['color'], 'sort' => $sort, 'keywords' => self::KEYWORDS[$data['slug']]],
            );

            foreach ($data['fields'] ?? [] as $fieldSort => $field) {
                $type->fields()->firstOrCreate(['key' => $field['key']], [...$field, 'sort' => $fieldSort]);
            }
        }
    }
}
