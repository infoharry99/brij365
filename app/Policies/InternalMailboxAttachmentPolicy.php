<?php
namespace App\Policies;
use App\Models\InternalMailboxAttachment;
use App\Models\User;
class InternalMailboxAttachmentPolicy
{
    public function view(User $user, InternalMailboxAttachment $attachment): bool
    {
        return $attachment->scan_status !== 'blocked'
            && app(InternalMailboxDispatchPolicy::class)->view($user, $attachment->dispatch);
    }
}
