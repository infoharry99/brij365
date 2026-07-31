<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollaborationMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message_number' => $this->message_number,
            'thread_key' => $this->thread_key,
            'parent_message_id' => $this->parent_message_id,
            'subject' => $this->subject,
            'body' => $this->body,
            'priority' => $this->priority,
            'status' => $this->status,
            'read_at' => $this->read_at?->toISOString(),
            'recipient_archived_at' => $this->recipient_archived_at?->toISOString(),
            'scheduled_for' => $this->scheduled_for?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'metadata' => $this->metadata ?? [],
            'chat_conversation' => $this->whenLoaded('chatConversation', fn (): ?array => $this->chatConversation ? [
                'id' => $this->chatConversation->id,
                'conversation_key' => $this->chatConversation->conversation_key,
                'type' => $this->chatConversation->type,
                'title' => $this->chatConversation->title,
            ] : null),
            'company' => $this->whenLoaded('company', fn (): array => [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null),
            'sender' => $this->whenLoaded('sender', fn (): array => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'email' => $this->sender->email,
            ]),
            'recipient' => $this->whenLoaded('recipient', fn (): array => [
                'id' => $this->recipient->id,
                'name' => $this->recipient->name,
                'email' => $this->recipient->email,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
