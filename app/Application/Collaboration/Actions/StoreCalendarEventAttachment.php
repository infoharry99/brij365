<?php
namespace App\Application\Collaboration\Actions;
use App\Domain\Collaboration\Services\CalendarAttachmentManager;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
final class StoreCalendarEventAttachment { public function __construct(private readonly CalendarAttachmentManager $manager) {} public function execute(CalendarEvent $event,User $actor,UploadedFile $file): CalendarEventAttachment { return $this->manager->store($event,$actor,$file); } }
