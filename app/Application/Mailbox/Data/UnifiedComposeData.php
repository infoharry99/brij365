<?php
namespace App\Application\Mailbox\Data;
use App\Models\User;
use Illuminate\Http\Request;
final readonly class UnifiedComposeData {
    public function __construct(public User $actor,public string $senderKey,public string $intent,public string $clientToken,public ?int $lockVersion,public ?int $projectId,public ?int $parentMessageId,public array $toUserIds,public array $ccUserIds,public array $bccUserIds,public array $toAddresses,public array $ccAddresses,public array $bccAddresses,public ?string $subject,public ?string $body,public string $priority,public ?string $scheduledFor,public array $attachments,public array $removeAttachmentIds,public ?Request $request){}
    public function allInternalRecipients():array{return array_values(array_unique(array_merge($this->toUserIds,$this->ccUserIds,$this->bccUserIds)));}
}
