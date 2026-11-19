<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Role;
use App\Modules\Integration\ApiClient;
use App\Modules\Integration\SendWebhook;
use App\Modules\Integration\WebhookSubscription;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Enums\LeadStatus;
use App\Modules\Leads\Enums\ReplyStatus;
use App\Modules\Leads\Models\Contact;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadType;
use App\Modules\Leads\Models\Reminder;
use App\Modules\Settings\Setting;
use Database\Seeders\LeadTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportsAndIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function lead(array $attributes = []): Lead
    {
        $contact = Contact::create(['phone' => '+9198'.random_int(10000000, 99999999), 'name' => 'Priya']);

        return Lead::create(['contact_id' => $contact->id, 'source' => LeadSource::WhatsappEnquiry, ...$attributes]);
    }

    public function test_report_figures_permissions_and_csv(): void
    {
        $this->seed(LeadTypeSeeder::class);
        $staff = User::factory()->create(['name' => 'Asha']);
        $admin = User::factory()->role(Role::Admin)->create();
        $birthday = LeadType::where('slug', 'birthday-party')->value('id');

        $converted = $this->lead([
            'lead_type_id' => $birthday, 'assigned_to' => $staff->id, 'status' => LeadStatus::Converted, 'reply_status' => ReplyStatus::Sent,
        ]);
        $converted->forceFill(['created_at' => now()->subHours(5), 'assigned_at' => now()->subHours(4), 'reply_sent_at' => now()->subHours(3)])->save();
        $this->lead(['status' => LeadStatus::Lost, 'lost_reason' => 'Price too high', 'source' => LeadSource::AgencyText]);
        $this->lead()->forceFill(['created_at' => now()->subDays(60)])->save();
        Reminder::create(['lead_id' => $converted->id, 'to_user_id' => $staff->id, 'channel' => 'in_app', 'auto' => false]);

        $this->actingAs($staff)->getJson('/api/reports')->assertForbidden();

        $this->actingAs($admin)->getJson('/api/reports')
            ->assertOk()
            ->assertJsonPath('data.totals.leads', 2)
            ->assertJsonPath('data.totals.converted', 1)
            ->assertJsonPath('data.totals.conversion_rate', 50)
            ->assertJsonPath('data.totals.avg_minutes_to_assign', 60)
            ->assertJsonPath('data.totals.avg_minutes_to_reply', 60)
            ->assertJsonPath('data.by_type.0.label', 'Birthday Party')
            ->assertJsonPath('data.by_assignee.0.label', 'Asha')
            ->assertJsonPath('data.by_assignee.0.reminders', 1)
            ->assertJsonPath('data.lost_reasons.0.label', 'Price too high');

        $this->getJson('/api/reports?from='.now()->subDays(90)->toDateString())->assertJsonPath('data.totals.leads', 3);

        $csv = $this->get('/api/reports/export')->assertOk()->streamedContent();
        $this->assertStringStartsWith('"Lead ID",Created,Customer,Phone', $csv);
        $this->assertCount(3, explode("\n", trim($csv)));
    }

    public function test_portal_tokens_are_isolated_from_staff_routes(): void
    {
        $lead = $this->lead(['status' => LeadStatus::Converted]);
        $client = ApiClient::create(['name' => 'Portal', 'active' => true]);
        $token = $client->createToken('portal', [ApiClient::ABILITY])->plainTextToken;
        $auth = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $this->getJson('/api/v1/leads?status=converted', $auth)->assertOk()->assertJsonPath('data.0.id', $lead->id)->assertJsonPath('data.0.customer.name', 'Priya');
        $this->getJson("/api/v1/leads/{$lead->id}", $auth)->assertOk()->assertJsonPath('data.status', 'converted');
        $this->getJson('/api/leads', $auth)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/leads')->assertUnauthorized();

        $client->update(['active' => false]);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/leads', $auth)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->role(Role::SuperAdmin)->create())->getJson('/api/v1/leads')->assertForbidden();
    }

    public function test_converting_a_lead_sends_a_signed_webhook_and_retries_failures(): void
    {
        Http::fake(['https://portal.example.com/*' => Http::sequence()->push('down', 503)->push(['ok' => true], 200)]);
        $hook = WebhookSubscription::create(['url' => 'https://portal.example.com/hooks/leads', 'secret' => 'shh', 'events' => ['lead.converted'], 'active' => true]);
        $lead = $this->lead();
        $staff = User::factory()->create();
        $lead->update(['assigned_to' => $staff->id]);

        try {
            $this->actingAs($staff)->postJson("/api/leads/{$lead->id}/status", ['status' => 'converted'])->assertOk();
        } catch (\RuntimeException) {
        }
        $this->assertSame(503, $hook->fresh()->last_status);

        (new SendWebhook($hook->id, 'lead.converted', ['id' => $lead->id]))->handle();
        $this->assertSame(200, $hook->fresh()->last_status);

        Http::assertSent(function (HttpRequest $request) {
            $body = $request->body();

            return $request->header('X-FunkyMonkey-Event')[0] === 'lead.converted'
                && $request->header('X-FunkyMonkey-Signature')[0] === 'sha256='.hash_hmac('sha256', $body, 'shh')
                && json_decode($body, true)['data']['id'] > 0;
        });
    }

    public function test_super_admin_manages_lead_types_and_fields(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->postJson('/api/lead-types', ['name' => 'Workshop', 'color' => 'blue', 'keywords' => [], 'fields' => []])->assertForbidden();

        $this->actingAs(User::factory()->role(Role::SuperAdmin)->create());
        $type = $this->postJson('/api/lead-types', [
            'name' => 'Summer Camp', 'color' => 'green', 'keywords' => ['Camp', ' summer '],
            'fields' => [
                ['key' => 'camp_week', 'label' => 'Week', 'field_type' => 'select', 'options' => ['Week 1', 'Week 2'], 'required' => true],
                ['key' => 'kids', 'label' => 'Kids', 'field_type' => 'number'],
            ],
        ])->assertCreated()->assertJsonPath('data.slug', 'summer-camp')->assertJsonPath('data.keywords', ['camp', 'summer'])->json('data');

        $weekId = $type['fields'][0]['id'];
        $this->putJson("/api/lead-types/{$type['id']}", [
            'name' => 'Summer Camp', 'color' => 'green', 'active' => false, 'keywords' => ['camp'],
            'fields' => [['id' => $weekId, 'key' => 'renamed_key', 'label' => 'Camp week', 'field_type' => 'select', 'options' => ['Week 1']]],
        ])->assertOk()->assertJsonCount(1, 'data.fields')->assertJsonPath('data.fields.0.key', 'camp_week')->assertJsonPath('data.active', false);

        $this->postJson('/api/lead-types', ['name' => 'Bad', 'color' => 'blue', 'keywords' => [], 'fields' => [['key' => 'Bad Key', 'label' => 'x', 'field_type' => 'text']]])
            ->assertUnprocessable()->assertJsonValidationErrors('fields.0.key');

        $this->lead(['lead_type_id' => $type['id']]);
        $this->deleteJson("/api/lead-types/{$type['id']}")->assertUnprocessable();
    }

    public function test_settings_validate_and_report_provider_status(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())->getJson('/api/settings')->assertForbidden();
        $this->actingAs(User::factory()->role(Role::SuperAdmin)->create());

        $this->putJson('/api/settings/reminder_rules', ['value' => ['auto_enabled' => true, 'after_hours' => 0, 'whatsapp_alert' => false]])
            ->assertUnprocessable();
        $this->putJson('/api/settings/reminder_rules', ['value' => ['auto_enabled' => true, 'after_hours' => 3, 'whatsapp_alert' => false]])->assertOk();
        $this->putJson('/api/settings/import_column_mapping', ['value' => ['phone' => 'Mobile', 'bogus' => 'x']])->assertUnprocessable();

        $this->assertSame(3, Setting::reminderRules()['after_hours']);
        $this->getJson('/api/settings')->assertOk()
            ->assertJsonPath('data.status.whatsapp_provider', 'mock')
            ->assertJsonPath('data.status.ai_key_configured', false);
        $this->postJson('/api/settings/provider/check')->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'mock'));
    }
}
