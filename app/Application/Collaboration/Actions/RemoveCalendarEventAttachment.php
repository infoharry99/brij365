<?php
namespace App\Application\Collaboration\Actions;
use App\Domain\Collaboration\Services\CalendarAttachmentManager;
use App\Models\CalendarEventAttachment;
final class RemoveCalendarEventAttachment { public function __construct(private readonly CalendarAttachmentManager $manager) {} public function execute(CalendarEventAttachment $attachment): void { $this->manager->remove($attachment); } }
