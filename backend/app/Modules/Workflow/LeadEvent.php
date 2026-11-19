<?php

namespace App\Modules\Workflow;

use App\Modules\Leads\Models\Lead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Tells open inboxes to refresh a lead ("lead.updated"); skips the browser that made the change. */
class LeadEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Lead $lead, public array $alsoUserIds = []) {}

    public static function send(Lead $lead, array $alsoUserIds = []): void
    {
        rescue(function () use ($lead, $alsoUserIds) {
            broadcast(new static($lead, $alsoUserIds))->toOthers();
        });
    }

    public function broadcastOn(): array
    {
        $channels = ['admins'];
        foreach (array_unique(array_filter([$this->lead->assigned_to, ...$this->alsoUserIds])) as $userId) {
            $channels[] = "users.{$userId}";
        }
        if ($this->lead->branch_id) {
            $channels[] = "branches.{$this->lead->branch_id}";
        }

        return array_map(fn ($name) => new PrivateChannel($name), $channels);
    }

    public function broadcastAs(): string
    {
        return 'lead.updated';
    }

    public function broadcastWith(): array
    {
        return ['lead_id' => $this->lead->id];
    }
}
