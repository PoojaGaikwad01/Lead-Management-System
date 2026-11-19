<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Role;
use App\Modules\Branches\Branch;
use App\Modules\Imports\Import;
use App\Modules\Leads\AiExtractor;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\Contact;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadType;
use App\Modules\Settings\Setting;
use Database\Seeders\LeadTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadCaptureTest extends TestCase
{
    use RefreshDatabase;

    private const AGENCY = '+919000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LeadTypeSeeder::class);
        Branch::create(['name' => 'Baner']);
        Branch::create(['name' => 'Wakad']);
        Setting::put('agency_numbers', [self::AGENCY]);
    }

    private function webhook(int $id, string $phone, ?string $text, array $attachments = []): void
    {
        $sender = ['name' => 'Sender', 'phone_number' => $phone, 'type' => 'contact'];
        $this->postJson('/api/webhooks/whatsapp/mock', [
            'event' => 'message_created', 'id' => $id, 'content' => $text, 'message_type' => 'incoming', 'private' => false,
            'sender' => $sender, 'conversation' => ['id' => 1, 'meta' => ['sender' => $sender]], 'attachments' => $attachments,
        ])->assertOk();
    }

    private function leadFor(string $phone): Lead
    {
        return Lead::whereHas('contact', fn ($q) => $q->where('phone', $phone))->sole();
    }

    public function test_customer_message_keywords_fill_type_and_branch_without_overriding(): void
    {
        $this->webhook(1, '+919876543210', 'Hi, I want to book a birthday party at Baner for 15 kids');

        $lead = $this->leadFor('+919876543210');
        $this->assertSame('birthday-party', $lead->leadType->slug);
        $this->assertSame('Baner', $lead->branch->name);

        $lead->update(['branch_id' => Branch::where('name', 'Wakad')->value('id')]);
        $this->webhook(2, '+919876543210', 'Actually is Baner open on Sunday? Also asking about membership');
        $this->assertSame('Wakad', $lead->fresh()->branch->name);
        $this->assertSame('birthday-party', $lead->fresh()->leadType->slug);
    }

    public function test_agency_fixed_format_text_creates_leads_and_merges_duplicates(): void
    {
        $existing = Lead::create(['contact_id' => Contact::create(['phone' => '+919123456789'])->id, 'source' => LeadSource::WhatsappEnquiry]);

        $this->webhook(10, self::AGENCY, "Name: Rahul Sharma\nPhone: 98765 43210\nType: Birthday Party\nBranch: Baner\nParty date: 12/12/2026\nKids: 15\n\nName: Sneha Patil\nPhone: +91 91234 56789\nType: Membership\nBranch: Wakad");

        $import = Import::sole();
        $this->assertSame(LeadSource::AgencyText, $import->source);
        $this->assertSame([2, 1, 1, 0], [$import->total_rows, $import->created_count, $import->duplicate_count, $import->error_count]);

        $rahul = $this->leadFor('+919876543210');
        $this->assertSame(LeadSource::AgencyText, $rahul->source);
        $this->assertSame('Rahul Sharma', $rahul->contact->name);
        $this->assertEqualsCanonicalizing(['party_date' => '2026-12-12', 'kids_count' => 15], $rahul->custom_data);
        $this->assertStringContainsString('Name: Rahul Sharma', $rahul->source_text);

        $existing->refresh();
        $this->assertSame('Wakad', $existing->branch->name);
        $this->assertSame('membership', $existing->leadType->slug);
        $this->assertSame('Sneha Patil', $existing->contact->name);
        $this->assertSame(2, Lead::count());
    }

    public function test_agency_free_text_falls_back_to_phone_detection(): void
    {
        $this->webhook(20, self::AGENCY, "Please call Amit Kulkarni 98220 12345 for a corporate team outing at Wakad");

        $lead = $this->leadFor('+919822012345');
        $this->assertSame('Amit Kulkarni', $lead->contact->name);
        $this->assertSame('corporate-group', $lead->leadType->slug);
        $this->assertSame('Wakad', $lead->branch->name);
    }

    public function test_agency_text_without_phone_is_recorded_as_an_import_error(): void
    {
        $this->webhook(30, self::AGENCY, 'Good morning! More leads coming later today.');

        $import = Import::sole();
        $this->assertSame(1, $import->error_count);
        $this->assertSame(0, Lead::count());
    }

    public function test_agency_csv_file_is_imported_automatically(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('simulator/x/leads.csv', "Customer Name,Mobile No,Enquiry,Location,Kids\nAsha,9822011111,Birthday,Baner,12\nRavi,98220 22222,Membership,Wakad,\nBad,12345,General,,\n");

        $this->webhook(40, self::AGENCY, null, [['file_type' => 'file', 'data_url' => 'mock://simulator/x/leads.csv']]);

        $import = Import::sole();
        $this->assertSame('completed', $import->status);
        $this->assertSame([3, 2, 0, 1], [$import->total_rows, $import->created_count, $import->duplicate_count, $import->error_count]);
        $this->assertSame(4, $import->errors[0]['row']);
        $this->assertSame(['kids_count' => 12], $this->leadFor('+919822011111')->custom_data);
        $this->assertSame(LeadSource::AgencyFile, $this->leadFor('+919822022222')->source);
    }

    public function test_manual_upload_maps_columns_dedupes_and_reports(): void
    {
        Storage::fake('local');
        $admin = User::factory()->role(Role::Admin)->create();
        $csv = "Full Name,Primary Contact,Notes\nPriya,9876500001,call evening\nPriya again,+91 98765 00001,dup\nNo Phone,,x\n";

        $this->actingAs(User::factory()->create())
            ->postJson('/api/imports', ['file' => UploadedFile::fake()->createWithContent('leads.csv', $csv)])
            ->assertForbidden();

        $id = $this->actingAs($admin)
            ->postJson('/api/imports', ['file' => UploadedFile::fake()->createWithContent('leads.csv', $csv)])
            ->assertCreated()
            ->assertJsonPath('data.status', 'needs_mapping')
            ->assertJsonPath('data.headers', ['Full Name', 'Primary Contact', 'Notes'])
            ->assertJsonCount(3, 'data.preview')
            ->json('data.id');

        $this->postJson("/api/imports/{$id}/run", ['mapping' => ['name' => 'Full Name', 'phone' => 'Nope']])->assertUnprocessable();

        $this->postJson("/api/imports/{$id}/run", ['mapping' => ['name' => 'Full Name', 'phone' => 'Primary Contact'], 'save_mapping' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.duplicate_count', 1)
            ->assertJsonPath('data.error_count', 1)
            ->assertJsonPath('data.errors.0.message', 'Phone number is missing.');

        $this->assertSame(LeadSource::ManualUpload, $this->leadFor('+919876500001')->source);
        $this->assertEqualsCanonicalizing(['name' => 'Full Name', 'phone' => 'Primary Contact'], Setting::get('import_column_mapping'));
        $this->postJson("/api/imports/{$id}/run", ['mapping' => ['phone' => 'Primary Contact']])->assertStatus(409);
        $this->getJson('/api/imports')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_lead_edit_validates_fields_and_only_admins_change_branch(): void
    {
        $staff = User::factory()->create();
        $lead = Lead::create(['contact_id' => Contact::create(['phone' => '+919876543210'])->id, 'source' => LeadSource::WhatsappEnquiry, 'assigned_to' => $staff->id]);
        $birthday = LeadType::where('slug', 'birthday-party')->value('id');

        $this->actingAs($staff);
        $this->patchJson("/api/leads/{$lead->id}", ['lead_type_id' => $birthday, 'custom_data' => ['kids_count' => 'many']])
            ->assertUnprocessable()->assertJsonValidationErrors('custom_data.kids_count');
        $this->patchJson("/api/leads/{$lead->id}", ['name' => 'Priya', 'lead_type_id' => $birthday, 'custom_data' => ['kids_count' => 20, 'unknown' => 'x']])
            ->assertOk()
            ->assertJsonPath('data.contact.name', 'Priya')
            ->assertJsonPath('data.custom_data', ['kids_count' => 20]);
        $this->patchJson("/api/leads/{$lead->id}", ['branch_id' => Branch::value('id')])->assertForbidden();

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->patchJson("/api/leads/{$lead->id}", ['branch_id' => Branch::value('id')])->assertOk();
    }

    public function test_ai_extraction_stays_off_without_an_api_key(): void
    {
        config(['lms.ai.enabled' => true, 'lms.ai.api_key' => null]);
        Setting::put('ai_extraction_enabled', true);

        $this->assertFalse(AiExtractor::enabled());
        $this->assertNull(app(AiExtractor::class)->extract('Rahul 9876543210 birthday'));
    }
}
