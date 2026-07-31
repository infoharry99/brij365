<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        $membership = $this->whenLoaded('activeMembers', fn () => $this->activeMembers->firstWhere('user_id', $currentUserId), null);
        $latest = $this->whenLoaded('chatMessages', fn () => $this->chatMessages->sortByDesc('created_at')->first(), null);

        return [
            'id' => $this->id,
            'conversation_key' => $this->conversation_key,
            'type' => $this->type,
            'title' => $request->user() ? $this->displayTitleFor($request->user()) : $this->title,
            'description' => $this->description,
            'visibility' => $this->visibility,
            'department' => $this->department,
            'status' => $this->status,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'unread_count' => $this->unread_count ?? 0,
            'member_count' => $this->active_members_count ?? $this->whenCounted('activeMembers'),
            'can_post' => (bool) ($membership?->can_post ?? false),
            'can_upload' => (bool) ($membership?->can_upload ?? false),
            'can_manage_members' => (bool) ($membership?->can_manage_members ?? false),
            'muted' => (bool) ($membership?->muted ?? false),
            'archived' => $membership?->archived_at !== null,
            'latest_message' => $latest instanceof ChatMessage ? [
                'id' => $latest->id,
                'message_number' => $latest->message_number,
                'body' => str($latest->body)->stripTags()->squish()->limit(180)->toString(),
                'sender' => $latest->relationLoaded('sender') && $latest->sender ? [
                    'id' => $latest->sender->id,
                    'name' => $latest->sender->name,
                    'email' => $latest->sender->email,
                ] : null,
                'type' => $latest->type,
                'created_at' => $latest->created_at?->toISOString(),
            ] : null,
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
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ] : null),
            'members' => $this->whenLoaded('activeMembers', fn () => $this->activeMembers->map(fn ($member): array => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'member_role' => $member->member_role,
                'can_post' => (bool) $member->can_post,
                'can_manage_members' => (bool) $member->can_manage_members,
                'user' => $member->relationLoaded('user') && $member->user ? [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'role' => $member->user->role?->name,
                ] : null,
            ])->values()->all()),
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
