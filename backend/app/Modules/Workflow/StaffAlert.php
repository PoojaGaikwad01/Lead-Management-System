<?php

namespace App\Modules\Workflow;

use App\Models\User;
use App\Modules\Connector\WhatsAppManager;
use App\Modules\Leads\Models\Contact;
use App\Modules\Settings\Setting;

/** Optional WhatsApp alerts to staff (off by default via reminder_rules.whatsapp_alert). */
class StaffAlert
{
    public function __construct(private WhatsAppManager $whatsapp) {}

    /** @param iterable<User> $users */
    public function send(iterable $users, string $text): bool
    {
        if (! Setting::reminderRules()['whatsapp_alert']) {
            return false;
        }

        foreach ($users as $user) {
            if ($user->phone) {
                rescue(fn () => $this->whatsapp->driver()->sendText(new Contact(['phone' => $user->phone, 'name' => $user->name]), $text));
            }
        }

        return true;
    }
}
