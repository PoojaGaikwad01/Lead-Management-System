# Funky Monkey Lead Management System – SPEC

## Goal

A separate web app (not part of the existing portal) that captures ALL types of leads from WhatsApp, shows them in a WhatsApp-style inbox, and manages assignment and follow-up.

## Stack

- Backend: [e.g. Laravel / Node.js (Express/Nest) / Django]
- Frontend: [e.g. React / Vue / Blade]
- DB: [MySQL / PostgreSQL]
- Real-time: WebSockets (or polling as fallback)
- WhatsApp: provider-agnostic connector. Default = Chatwoot (WhatsApp Cloud API). Must be swappable to Periskope via config.

## Lead sources

1. Customers messaging the business WhatsApp number (via provider webhook).
2. The ad agency's WhatsApp number (whitelisted in settings) sending leads as text OR Excel/CSV files.
3. Manual Excel/CSV upload by admin (backup).
   Every lead stores: source (WhatsApp enquiry / Agency text / Agency file / Manual upload).

## Lead types (admin-configurable, no code changes)

Default: Birthday Party, Play Zone Visit, Membership, Events/School Trip, Corporate/Group, Franchise, General.
Each type can have custom fields (e.g. Birthday: party_date, kids_count; Franchise: city, budget).
Store custom field definitions in DB; store values as JSON per lead.

## Lead capture rules

- New message from an unknown number → create contact + lead (status New).
- Message from a known number with an open lead → attach to that lead.
- Agency text → parse into one or more leads. Use a simple parser for fixed formats; fall back to AI extraction (Claude API, JSON output) for free text.
- Agency Excel/CSV → download, read rows, map columns (saved mapping in settings), normalize phones to +91, validate, skip/merge duplicates by phone, show an import summary (created / duplicates / errors).
- AI extraction (optional, toggle in settings): fill lead type, branch, and type-specific fields from message text. Admin can always edit.
- Dedupe by normalized phone number everywhere.

## Roles

- Super admin: everything, including settings, users, lead types, provider config.
- Admin: all leads; assign/reassign; reply; send reminders; reports.
- Assignee (branch manager / staff): only leads assigned to them or their branch; mark reply sent; update status; notes; call log.
  Enforce permissions on the backend.

## Workflow

1. Lead arrives → status New, unassigned.
2. Admin manually assigns to a user/branch. If no location, admin handles it directly.
3. On assign → reply_status = not_sent; notify assignee (in-app; optional WhatsApp alert).
4. Assignee contacts the customer (WhatsApp from the app, or call) → clicks "Mark reply sent" (optional note).
5. Admin sees a "Reply not sent" tab → "Send reminder" (in-app + optional WhatsApp). Track reminder_count, last_reminder_at.
6. Optional auto-reminder after X hours (configurable, off by default).
7. Statuses: New, Assigned, Contacted, Follow-up, Converted, Lost (reason required), Closed.

## Data model (minimum)

users, branches, contacts(phone unique, name), lead_types, lead_type_fields, leads(contact_id, lead_type_id, branch_id, source, assigned_to, assigned_by, assigned_at, status, reply_status, reply_sent_at, reply_sent_by, reminder_count, last_reminder_at, custom_data JSON, lost_reason), messages(lead_id, contact_id, direction, type, body, media_url, provider_message_id unique, status, sent_at), imports(file, uploaded_by/source, counts, errors JSON), activity_log(lead_id, user_id, action, details, created_at), settings.

## Screens

1. Login.
2. Inbox (WhatsApp-style, the main screen):
   - Left: lead/chat list with name, phone, last message, time, unread count, source tag, type chip, status chip; search and filters (type, source, branch, assignee, status, reply status, date); tabs: New, Reply not sent, My leads, All.
   - Middle: chat bubbles (in left / out right), timestamps, date separators, delivery ticks, media (image, audio player, PDF/Excel file bubble with download), reply box.
   - Right: lead details (editable type + custom fields), assignee, reply status, reminder history, actions (Assign, Send reminder, Call, Mark reply sent, Change status), notes, activity log.
3. Imports: upload Excel/CSV, column mapping, import history and summaries.
4. Reports: leads by type/source/branch/assignee, conversion rate, avg time to assign, avg time to reply, reminders per assignee, lost reasons.
5. Settings (super admin): users, branches, lead types and fields, agency numbers, provider config, AI toggle, reminder rules.

## UI/UX

- WhatsApp-like layout but Funky Monkey branding (no WhatsApp logo/trademarks).
- Desktop: 3 panels. Mobile: list → full-screen chat → details drawer; sticky action bar; large tap targets.
- Colour-coded badges with text labels (never colour alone). Overdue items clearly highlighted ("3 hrs overdue").
- Counters on tabs and menu. Summary cards on top of the inbox (New today, Unassigned, Reply not sent, Converted this week).
- Toasts for actions; confirm only for destructive/important ones.
- Proper empty, loading (skeleton) and error (with retry) states.
- Accessible: contrast, labels, keyboard navigation.

## Integration (later)

REST API + outgoing webhook so the existing Funky Monkey portal can receive converted leads (e.g. create a booking). Optional SSO later.

## Non-functional

- Verify webhook authenticity; idempotent processing (provider_message_id unique).
- Secrets in .env only.
- Log provider/API errors; show friendly messages.
- Keep code modular: connector/, leads/, imports/, inbox/, reports/, settings/.
