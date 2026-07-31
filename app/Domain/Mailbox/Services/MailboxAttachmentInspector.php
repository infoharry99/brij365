<?php
namespace App\Domain\Mailbox\Services;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
final class MailboxAttachmentInspector {
    public function inspect(UploadedFile $file):string { if((bool)config('mailbox.malware_scan_required',false))throw ValidationException::withMessages(['attachments'=>'Attachment scanning is required but no approved scanner is available.']);return 'not_required'; }
}
