<?php

namespace App\Http\Resources;

use App\Models\ChatPollOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\Collaboration\ChatAccessService;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $userId = $user?->id;
        $poll = $this->whenLoaded('poll', fn () => $this->poll, null);

        return [
            'id' => $this->id,
            'conversation_id' => $this->chat_conversation_id,
            'message_number' => $this->message_number,
            'type' => $this->type,
            'body' => $this->body,
            'priority' => $this->priority,
            'status' => $this->status,
            'parent_message_id' => $this->parent_message_id,
            'parent' => ($this->relationLoaded('parent') && $this->parent) || $this->parent ? [
                'id' => $this->parent?->id,
                'message_number' => $this->parent?->message_number,
                'sender' => $this->parent?->sender?->name ?? 'Message',
                'body' => $this->parent?->body ? str($this->parent->body)->squish()->limit(90)->toString() : 'Attachment',
            ] : null,
            'sender' => $this->relationLoaded('sender') && $this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'email' => $this->sender->email,
                'role' => $this->sender->role?->name,
            ] : null,
            'attachments' => $this->whenLoaded('attachments', function () use ($request): array {
                $isApi = $request->is('api/*') || $request->expectsJson() || $request->bearerToken() !== null;

                return $this->attachments->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'type' => $attachment->type,
                    'filename' => $attachment->original_filename,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'size_label' => $this->sizeLabel((int) $attachment->size_bytes),
                    'duration_seconds' => $attachment->duration_seconds,
                    'scan_status' => $attachment->scan_status,
                    'download_url' => $isApi
                        ? route('api.chat.attachments.download', $attachment)
                        : route('collaboration.chat.attachments.download', $attachment),
                    'preview_url' => (str_starts_with((string) $attachment->mime_type, 'image/') || str_starts_with((string) $attachment->mime_type, 'audio/'))
                        ? ($isApi ? route('api.chat.attachments.preview', $attachment) : route('collaboration.chat.attachments.preview', $attachment))
                        : null,
                    'can_download' => $attachment->scan_status !== 'blocked',
                ])->values()->all();
            }),
            'poll' => $poll ? [
                'id' => $poll->id,
                'question' => $poll->question,
                'allows_multiple' => (bool) $poll->allows_multiple,
                'status' => $poll->status,
                'closes_at' => $poll->closes_at?->toISOString(),
                'total_votes' => $poll->relationLoaded('votes') ? $poll->votes->count() : 0,
                'can_vote' => $poll->status === 'open' && $user && app(ChatAccessService::class)->can($user, 'can_vote_poll'),
                'can_close_poll' => $poll->status === 'open' && $user && (
                    (int) $poll->created_by_user_id === (int) $userId
                    || app(ChatAccessService::class)->can($user, 'can_manage_members')
                ),
                'options' => $poll->relationLoaded('options') ? $poll->options->map(fn (ChatPollOption $option): array => [
                    'id' => $option->id,
                    'label' => $option->option_text,
                    'votes' => $option->relationLoaded('votes') ? $option->votes->count() : 0,
                    'voted_by_me' => $option->relationLoaded('votes') ? $option->votes->contains(fn ($vote) => (int) $vote->voter_user_id === (int) $userId) : false,
                ])->values()->all() : [],
            ] : null,
            'reactions' => $this->whenLoaded('reactions', fn () => $this->reactions
                ->groupBy('emoji')
                ->map(fn ($rows, $emoji): array => [
                    'emoji' => $emoji,
                    'count' => $rows->count(),
                    'me' => $rows->contains(fn ($row) => (int) $row->user_id === (int) $userId),
                ])
                ->values()
                ->all()),
            'read_receipts' => $this->whenLoaded('reads', fn () => $this->reads->map(fn ($read): array => [
                'user_id' => $read->user_id,
                'delivered_at' => $read->delivered_at?->toISOString(),
                'read_at' => $read->read_at?->toISOString(),
            ])->values()->all()),
            'can_edit' => false,
            'can_delete' => $user ? app(\App\Services\Collaboration\ChatConnectService::class)->canDeleteMessage($user, $this->resource) : false,
            'can_download' => true,
            'sent_at' => $this->sent_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function sizeLabel(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return max(1, (int) ceil($bytes / 1024)).' KB';
    }
}
