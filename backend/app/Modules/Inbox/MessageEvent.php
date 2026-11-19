<?php

namespace App\Modules\Inbox;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Broadcast as "message.created" or "message.updated" to admins and the lead's assignee/branch. */
class MessageEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Message $message, public string $name) {}

    public static function send(Message $message, string $name): void
    {
        rescue(function () use ($message, $name) {
            broadcast(new static($message->loadMissing('contact', 'lead'), $name))->toOthers();
        });
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('admins')];
        $lead = $this->message->lead;

        if ($lead?->assigned_to) {
            $channels[] = new PrivateChannel("users.{$lead->assigned_to}");
        }
        if ($lead?->branch_id) {
            $channels[] = new PrivateChannel("branches.{$lead->branch_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    public function broadcastWith(): array
    {
        return ['message' => (new MessageResource($this->message))->resolve()];
    }
}
