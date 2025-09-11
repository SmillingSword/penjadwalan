<?php

namespace App\Events;

use App\Models\Event;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReminderTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $event;
    public $reminder;
    public $user;

    public function __construct(Event $event, Reminder $reminder, User $user)
    {
        $this->event = $event;
        $this->reminder = $reminder;
        $this->user = $user;
    }

    public function broadcastOn()
    {
        return ["private-user.{$this->user->id}"];
    }

    public function broadcastAs()
    {
        return 'reminder.triggered';
    }

    public function broadcastWith()
    {
        return [
            'reminder' => [
                'id' => $this->reminder->id,
                'minutes_before' => $this->reminder->minutes_before,
                'method' => $this->reminder->method,
            ],
            'event' => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'start_at' => $this->event->start_at->toISOString(),
                'location' => $this->event->location,
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
        ];
    }
}
