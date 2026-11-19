# Lead-Management-System

Funky Monkey lead management app. Requirements: [SPEC.md](SPEC.md). Build plan: [PLAN.md](PLAN.md).

## Local setup

Requires PHP 8.3+, Composer, Node.js 20+ and MySQL 8.4 (database `funky_leads`). Redis is optional locally (queue/cache default to the database driver); `docker compose up -d` starts MySQL and Redis where Docker is available.

```sh
# backend (http://127.0.0.1:8000)
cd backend
composer install
cp .env.example .env            # then set DB_PASSWORD and SUPER_ADMIN_PASSWORD (ANTHROPIC_API_KEY is optional)
php artisan key:generate
php artisan reverb:install      # generates REVERB_* keys
php artisan migrate --seed
php artisan serve
php artisan queue:work          # processes WhatsApp webhooks (separate terminal)
php artisan reverb:start        # real-time server, ws://localhost:8080 (separate terminal)
php artisan schedule:work       # runs the auto-reminder check every 5 minutes (separate terminal)

# frontend (http://localhost:5173, proxies /api to the backend)
cd frontend
npm install
npm run dev
```

Tests: `cd backend && php artisan test` · `cd frontend && npm test`

## Going live with Chatwoot

1. **Server:** HTTPS domain (e.g. `https://leads.funkymonkey.in`), PHP 8.3 + MySQL 8.4, `npm run build` in `frontend/` served from the same domain with `/api` and `/broadcasting` routed to Laravel. Keep `php artisan queue:work`, `php artisan reverb:start` and the scheduler (`* * * * * php artisan schedule:run`) running under Supervisor/systemd.
2. **`backend/.env`:**
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://leads.funkymonkey.in
   SANCTUM_STATEFUL_DOMAINS=leads.funkymonkey.in
   SESSION_SECURE_COOKIE=true
   REVERB_HOST=leads.funkymonkey.in   REVERB_PORT=443   REVERB_SCHEME=https   (proxy wss to Reverb)
   WHATSAPP_PROVIDER=chatwoot
   CHATWOOT_BASE_URL=https://app.chatwoot.com        # or your self-hosted Chatwoot
   CHATWOOT_ACCOUNT_ID=<account id from the Chatwoot URL>
   CHATWOOT_INBOX_ID=<id of the WhatsApp inbox>
   CHATWOOT_API_TOKEN=<Profile settings → Access token>
   CHATWOOT_WEBHOOK_TOKEN=<any long random string>
   ```
   Then run `php artisan config:cache && php artisan migrate --force`, and set a real `SUPER_ADMIN_PASSWORD` before seeding.
3. **Chatwoot → Settings → Integrations → Webhooks → Add:** URL `https://leads.funkymonkey.in/api/webhooks/whatsapp/chatwoot?token=<CHATWOOT_WEBHOOK_TOKEN>`, events **Message created** and **Message updated**.
4. **Check:** Settings → General → *Test connection* should name your WhatsApp inbox. Send a WhatsApp message to the business number and confirm it appears in the Inbox; reply from the app and confirm it reaches the phone.
5. **In the app:** add branches and staff (with mobile numbers), the agency's WhatsApp number(s), and adjust reply time/reminder rules. The Simulator menu disappears in the production build.
6. **Portal:** Settings → Portal integration → create an API client (token for `GET /api/v1/leads`) and add the portal's webhook URL for `lead.converted`; share the token, secret and signature snippet with the portal developer.
