<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormSchemaUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $formId,
        public readonly array $schema,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("forms.{$this->formId}");
    }

    public function broadcastAs(): string
    {
        return 'schema.updated';
    }
}
