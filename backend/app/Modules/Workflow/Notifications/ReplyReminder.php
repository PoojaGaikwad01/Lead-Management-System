<?php

namespace App\Modules\Workflow\Notifications;

use App\Modules\Leads\Models\Lead;
use Illuminate\Notifications\Notification;

class ReplyReminder extends Notification
{
    public function __construct(public Lead $lead, public bool $auto) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $lead = $this->lead;

        return [
            'kind' => 'reminder',
            'lead_id' => $lead->id,
            'title' => ($this->auto ? 'Automatic reminder' : 'Reminder').': reply to '.($lead->contact->name ?? $lead->contact->phone),
            'body' => 'Assigned '.$lead->assigned_at?->diffForHumans().'. Reply to the customer, then mark the reply as sent.',
        ];
    }
}
