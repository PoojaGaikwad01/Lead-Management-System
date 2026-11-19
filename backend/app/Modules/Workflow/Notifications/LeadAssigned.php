<?php

namespace App\Modules\Workflow\Notifications;

use App\Modules\Leads\Models\Lead;
use Illuminate\Notifications\Notification;

class LeadAssigned extends Notification
{
    public function __construct(public Lead $lead) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $lead = $this->lead;
        $target = $lead->assigned_to === $notifiable->id ? 'you' : ($lead->branch?->name ?? 'your branch');

        return [
            'kind' => 'assigned',
            'lead_id' => $lead->id,
            'title' => "New lead assigned to {$target}",
            'body' => ($lead->contact->name ?? $lead->contact->phone).' · '.($lead->leadType?->name ?? 'Type not set'),
        ];
    }
}
