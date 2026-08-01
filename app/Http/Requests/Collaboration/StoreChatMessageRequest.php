<?php

namespace App\Http\Requests\Collaboration;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Collaboration\ChatAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ChatConversation|null $conversation */
        $conversation = $this->route('chatConversation');

        return $conversation instanceof ChatConversation
            && ($this->user()?->can('post', $conversation) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('attachments') && ! is_array($this->file('attachments'))) {
            $this->files->set('attachments', [$this->file('attachments')]);
        } elseif ($this->hasFile('attachment')) {
            $existing = is_array($this->file('attachments')) ? $this->file('attachments') : [];
            $this->files->set('attachments', array_merge($existing, [$this->file('attachment')]));
        } elseif ($this->hasFile('file')) {
            $existing = is_array($this->file('attachments')) ? $this->file('attachments') : [];
            $this->files->set('attachments', array_merge($existing, [$this->file('file')]));
        }
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:10000'],
            'message_type' => ['nullable', 'string', Rule::in(['text', 'file', 'voice_note'])],
            'parent_message_id' => ['nullable', 'integer', Rule::exists('chat_messages', 'id')],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high', 'critical'])],
            'metadata' => ['nullable', 'array'],
            'metadata.mentions' => ['nullable', 'array', 'max:25'],
            'metadata.mentions.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'metadata.forwarded_from_message_id' => ['nullable', 'integer', Rule::exists('chat_messages', 'id')],
            'metadata.forwarded_from_conversation_id' => ['nullable', 'integer', Rule::exists('chat_conversations', 'id')],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'max:25600',
                'mimes:jpg,jpeg,png,gif,webp,bmp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,mp3,wav,m4a,aac,ogg,oga,webm,3gp,3gpp,amr,flac,opus,wma,mp4,mov,avi,mkv,3g2',
            ],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:600'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();
                /** @var ChatConversation|null $conversation */
                $conversation = $this->route('chatConversation');

                if (! $actor || ! $conversation || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->filled('parent_message_id')) {
                    $parent = ChatMessage::query()
                        ->whereKey($this->integer('parent_message_id'))
                        ->first();

                    if (! $parent || (int) $parent->chat_conversation_id !== (int) $conversation->id) {
                        $validator->errors()->add('parent_message_id', 'Replies must belong to the selected conversation.');
                    }
                }

                $hasBody = trim((string) $this->input('body', '')) !== '';
                $hasAttachments = $this->hasFile('attachments');
                if (! $hasBody && ! $hasAttachments) {
                    $validator->errors()->add('body', 'Enter a message or attach a file.');
                }

                $access = app(ChatAccessService::class);
                if ($hasAttachments && ! $access->can($actor, 'can_upload')) {
                    $validator->errors()->add('attachments', 'File sharing is not available for your role.');
                }

                if (($this->input('message_type') === 'voice_note') && ! $access->can($actor, 'can_send_voice')) {
                    $validator->errors()->add('attachments', 'Voice notes are not available for your role.');
                }

                $mentionIds = collect($this->input('metadata.mentions', []))
                    ->map(fn ($id): int => (int) $id)
                    ->unique();
                if ($mentionIds->isNotEmpty()) {
                    $memberIds = $conversation->activeMembers()->pluck('user_id')->map(fn ($id): int => (int) $id);
                    if ($mentionIds->diff($memberIds)->isNotEmpty()) {
                        $validator->errors()->add('metadata.mentions', 'Mentions are limited to active conversation members.');
                    }
                }

                $forwardedMessageId = (int) $this->input('metadata.forwarded_from_message_id', 0);
                $forwardedConversationId = (int) $this->input('metadata.forwarded_from_conversation_id', 0);
                if (($forwardedMessageId && ! $forwardedConversationId) || (! $forwardedMessageId && $forwardedConversationId)) {
                    $validator->errors()->add('metadata', 'Forwarded message context is incomplete.');
                } elseif ($forwardedMessageId && $forwardedConversationId) {
                    $sourceMessage = ChatMessage::query()->whereKey($forwardedMessageId)->first();
                    if (
                        ! $sourceMessage
                        || (int) $sourceMessage->chat_conversation_id !== $forwardedConversationId
                        || ! $actor->can('view', $sourceMessage->conversation)
                    ) {
                        $validator->errors()->add('metadata', 'The forwarded message is not available for your access.');
                    }
                }
            },
        ];
    }
}
