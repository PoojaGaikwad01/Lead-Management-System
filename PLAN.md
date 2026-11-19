# Funky Monkey Lead Management System – PLAN

Source of truth for requirements: [SPEC.md](SPEC.md). Mark each phase `[x]` when it is complete.

## Stack (decided)

| Layer | Choice | Why it fits the spec |
|---|---|---|
| Backend | Laravel (current stable), PHP 8.3+ | Built-in auth, policies (backend permissions), queues (webhook processing, imports), scheduler (auto-reminders), notifications |
| Auth | Laravel Sanctum | Cookie auth for the SPA + API tokens for the portal API |
| Frontend | React + TypeScript + Vite + Tailwind CSS | 3-panel responsive inbox; TanStack Query for data, Recharts for reports |
| DB | MySQL 8 | JSON columns for `custom_data`, settings, import errors |
| Queue / cache | Redis | Queue workers, counters cache, broadcast backend |
| Real-time | Laravel Reverb (WebSockets) + Laravel Echo | Polling fallback if the socket drops |
| Excel/CSV | Laravel Excel (PhpSpreadsheet) | Chunked reads, row validation |
| AI extraction | Claude API, JSON output; model and key in `.env` | Fully optional: `AI_EXTRACTION_ENABLED=false` by default; the app runs and parses leads without `ANTHROPIC_API_KEY` |
| Tests | PHPUnit (backend), Vitest (frontend) | |
| Dev env | MySQL 8.4 locally (tests use in-memory SQLite); Redis via Memurai when installed, database queue/cache until then; Docker Compose optional | |

### Assumptions
- Chatwoot is not set up yet: `WHATSAPP_PROVIDER=mock` is the default. The Chatwoot connector is built and tested against sample payloads but stays unused until its credentials are added to `.env`.
- No Anthropic API key yet: AI extraction is off (`AI_EXTRACTION_ENABLED=false`) and every capture path works with keyword/pattern parsing alone.
- The existing Funky Monkey portal integrates only through the REST API and outgoing webhooks (phase 6), so its stack does not affect this app.

## Folder structure

```
backend/                      Laravel API
  app/Modules/
    Auth/  Users/  Branches/
    Connector/   WhatsAppProvider interface, ChatwootProvider, PeriskopeProvider, MockProvider, webhook verification
    Leads/       Contact, Lead, LeadType, LeadTypeField, LeadCaptureService, PhoneNormalizer, Dedupe
    Inbox/       Messages, send/receive, broadcast events
    Imports/     Excel/CSV importer, AgencyTextParser, AiExtractor
    Workflow/    Assign, reply status, reminders, notifications, auto-reminder job
    Reports/
    Settings/
    Integration/ Portal API, outgoing webhooks
  database/migrations, database/seeders (default lead types, super admin)
  routes/api.php, routes/channels.php
frontend/                     React SPA
  src/features/{auth,inbox,leads,imports,reports,settings}
  src/components              UI kit, badges, skeletons, toasts
  src/lib/{api,realtime}
docker-compose.yml
.env.example
```

## Database tables

| Table | Key columns |
|---|---|
| users | name, email, password, role (super_admin / admin / assignee), branch_id, phone, active |
| branches | name, address, phone, active |
| contacts | phone (E.164, unique), name |
| lead_types | name, slug, color, sort, active |
| lead_type_fields | lead_type_id, key, label, field_type, options JSON, required, sort |
| leads | contact_id, lead_type_id, branch_id, source, assigned_to, assigned_by, assigned_at, status, reply_status, reply_sent_at, reply_sent_by, reminder_count, last_reminder_at, custom_data JSON, lost_reason, last_message_at, unread_count, converted_at, closed_at |
| messages | lead_id, contact_id, direction, type, body, media_url, provider_message_id (unique), status, sent_at, read_at, raw JSON |
| lead_notes | lead_id, user_id, kind (note / call), body, call_outcome |
| reminders | lead_id, sent_by, to_user_id, channel (in_app / whatsapp), auto (bool) |
| notifications | Laravel standard notifications table |
| imports | source, file, uploaded_by, mapping JSON, created / duplicate / error counts, errors JSON, status |
| activity_log | lead_id, user_id, action, details JSON, created_at |
| settings | key, value JSON (agency numbers, provider config, AI toggle, reminder rules, column mappings) |
| webhook_events | provider, event_id (unique), payload, processed_at — idempotency and debugging |
| api_clients, webhook_subscriptions | Portal API keys and outgoing webhook URLs (phase 6) |

## Main endpoints (`/api`)

- **Auth:** `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`
- **Incoming webhook:** `POST /webhooks/whatsapp/{provider}` — verify signature, store event, process on queue
- **Dev simulator (local env only, super admin):** `POST /dev/simulate/{customer-message|agency-text|agency-file}` — builds a Chatwoot-style webhook payload and feeds it through the same webhook pipeline
- **Inbox:** `GET /leads` (tabs + filters), `GET /leads/summary` (tab counters + summary cards + unread count), `GET /lead-types`, `GET|PATCH /leads/{id}`, `GET|POST /leads/{id}/messages`, `POST /leads/{id}/read`, `GET /media/{message}`
- **Workflow:** `POST /leads/{id}/assign`, `/mark-reply-sent`, `/remind`, `/status`, `/notes`; `GET /leads/{id}/activity`
- **Imports:** `POST /imports` (upload + suggested mapping), `POST /imports/{id}/run` (confirmed mapping), `GET /imports`, `GET /imports/{id}`
- **Admin:** CRUD `/users`, `/branches`, `/lead-types` (fields saved with their type); `GET /settings`, `PUT /settings/{key}`, `POST /settings/provider/check`; `/integrations` (API clients, webhooks, test send)
- **Notifications:** `GET /notifications`, `POST /notifications/read`
- **Reports:** `GET /reports?from&to` (all figures in one response), `GET /reports/export?from&to` (lead-level CSV)
- **Portal API (token):** `GET /v1/leads`, `GET /v1/leads/{id}`; outgoing `lead.converted` webhook
- **Broadcast channels:** `user.{id}`, `branch.{id}`, `admins`

## Phases

- [x] **Phase 1 – Setup, auth, roles, branches**
  Repo + Docker Compose, all migrations, seeders (default lead types, super admin), Sanctum login/logout, role policies enforced on the backend (assignee sees only own/branch leads), users and branches CRUD + screens.

- [x] **Phase 2 – WhatsApp connector, webhook, messages**
  `WhatsAppProvider` interface with implementations selected by `WHATSAPP_PROVIDER`: **Mock (default)**, Chatwoot (code-complete, tested with sample payloads, enabled once credentials exist), Periskope. Mock connector accepts Chatwoot-shaped webhook payloads and "sends" by recording outgoing messages locally (with simulated delivery/read status updates). Dev-only simulator page (Settings → Developer, visible only when `APP_ENV=local`) to fire realistic Chatwoot-style payloads: customer message (text / image / audio / PDF), agency text message, and agency Excel/CSV file attachment, from a chosen phone number. Webhook signature verification; idempotent processing via `provider_message_id` / `webhook_events`; store inbound messages and media; send text and media; provider error logging.

- [x] **Phase 3 – WhatsApp-style inbox UI**
  Desktop 3 panels; mobile list → full-screen chat → details drawer with sticky action bar; tabs (New, Reply not sent, My leads, All) with counters; search and filters; summary cards; chat bubbles, delivery ticks, date separators, image / audio / file bubbles; reply box; real-time updates; empty, skeleton and error-with-retry states; keyboard and contrast accessibility; Funky Monkey branding.

- [x] **Phase 4 – Lead capture**
  Unknown number → contact + lead (New); known number with open lead → attach; agency number whitelist; agency text parser using keyword/pattern rules only (labelled fields like `Name:` / `Phone:` / `Branch:`, phone-number detection, keyword → lead type and branch-name matching, multiple leads per message); every agency message is recorded as an import (text with no phone number shows as an import error with the original message); agency and manual Excel/CSV import with saved column mapping, +91 normalisation, validation, dedupe/merge by phone and an import summary; admin-configurable lead types and custom fields. **AI extraction is optional and off by default** (`AI_EXTRACTION_ENABLED=false`): when enabled and `ANTHROPIC_API_KEY` is set, Claude fills gaps the parser left; if either is missing, or a call fails, the parser result is used and nothing breaks.

- [x] **Phase 5 – Assignment, reply status, reminders, notifications**
  Assign/reassign to user or branch; `reply_status` flow and "Mark reply sent"; "Reply not sent" tab with overdue labels; manual reminders (in-app + optional WhatsApp) tracking `reminder_count` / `last_reminder_at`; optional auto-reminder after X hours (off by default); status changes (reason required for Lost); notes and call log; activity log; in-app notifications.

- [x] **Phase 6 – Reports, settings, portal API**
  Reports and charts (by type/source/branch/assignee, conversion rate, avg time to assign/reply, reminders per assignee, lost reasons); full super-admin settings (provider, AI toggle, reminder rules, agency numbers, column mappings); portal REST API with tokens; signed outgoing webhooks with retries.
