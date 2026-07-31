<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_number' => $this->notification_number,
            'channel' => $this->channel,
            'category' => $this->category,
            'severity' => $this->severity,
            'status' => $this->status,
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->action_url,
            'notifiable_type' => $this->notifiable_type,
            'notifiable_id' => $this->notifiable_id,
            'payload' => $this->payload ?? [],
            'read_at' => $this->read_at?->toISOString(),
            'archived_at' => $this->archived_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'recipient' => $this->whenLoaded('recipient', fn (): array => [
                'id' => $this->recipient->id,
                'name' => $this->recipient->name,
                'email' => $this->recipient->email,
            ]),
            'triggered_by' => $this->whenLoaded('triggeredBy', fn (): ?array => $this->triggeredBy ? [
                'id' => $this->triggeredBy->id,
                'name' => $this->triggeredBy->name,
                'email' => $this->triggeredBy->email,
            ] : null),
        ];
    }
}
