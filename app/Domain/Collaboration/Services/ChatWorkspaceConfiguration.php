<?php

namespace App\Domain\Collaboration\Services;

use App\Models\User;
use App\Services\Collaboration\ChatAccessService;

final class ChatWorkspaceConfiguration
{
    public function __construct(
        private readonly ChatAccessService $access,
        private readonly CollaborationWorkspaceOptions $workspace,
    ) {}

    /** @return array<string,mixed> */
    public function for(User $user): array
    {
        $capabilities = $this->access->capabilitiesFor($user);

        return [
            'enabled' => $this->access->canView($user),
            'read_only' => (bool) ($capabilities['read_only'] ?? true),
            'can_post' => (bool) ($capabilities['can_post'] ?? false),
            'can_create_dm' => (bool) ($capabilities['can_create_dm'] ?? false),
            'can_create_group' => (bool) ($capabilities['can_create_group'] ?? false),
            'can_create_channel' => (bool) ($capabilities['can_create_channel'] ?? false),
            'can_upload' => (bool) ($capabilities['can_upload'] ?? false),
            'can_send_voice' => (bool) ($capabilities['can_send_voice'] ?? false),
            'can_create_poll' => (bool) ($capabilities['can_create_poll'] ?? false),
            'can_vote_poll' => (bool) ($capabilities['can_vote_poll'] ?? false),
            'capabilities' => [
                'upload' => (bool) ($capabilities['can_upload'] ?? false),
                'voice' => (bool) ($capabilities['can_send_voice'] ?? false),
                'poll' => (bool) ($capabilities['can_create_poll'] ?? false),
                'vote_poll' => (bool) ($capabilities['can_vote_poll'] ?? false),
                'manage_members' => (bool) ($capabilities['can_manage_members'] ?? false),
                'archive' => (bool) ($capabilities['can_archive'] ?? false),
                'export' => (bool) ($capabilities['can_export'] ?? false),
                'realtime' => filled(config('broadcasting.connections.reverb.key')),
            ],
            'current_user_id' => $user->id,
            'reverb' => [
                'enabled' => filled(config('broadcasting.connections.reverb.key')),
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host', '127.0.0.1'),
                'port' => (int) config('broadcasting.connections.reverb.options.port', 8080),
                'scheme' => config('broadcasting.connections.reverb.options.scheme', 'http'),
                'channel_prefix' => 'chat.conversation.',
            ],
        ];
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    public function users(User $user): \Illuminate\Support\Collection
    {
        return $this->workspace->internalUsers($user)
            ->reject(fn (User $option): bool => (int) $option->id === (int) $user->id)
            ->filter(fn (User $option): bool => $this->access->canView($option))
            ->values();
    }
}
