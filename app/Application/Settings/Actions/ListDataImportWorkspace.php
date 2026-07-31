<?php
namespace App\Application\Settings\Actions;
use App\Application\Settings\Data\SettingsWorkspaceData;
use App\Domain\Settings\Services\SettingsRegister;
use App\Models\DataImportBatch;
use App\Models\User;
final class ListDataImportWorkspace {
 public function __construct(private readonly SettingsRegister $register) {}
 public function execute(User $actor,array $filters): SettingsWorkspaceData { return new SettingsWorkspaceData('imports',$this->register->imports($actor,$filters),$filters,$this->register->companies($actor),statuses:[DataImportBatch::STATUS_PREVIEW=>'Preview',DataImportBatch::STATUS_POSTED=>'Posted',DataImportBatch::STATUS_FAILED=>'Failed'],types:[DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES=>'CRM Prospect Inquiries',DataImportBatch::TYPE_HR_EMPLOYEES=>'HR Employees'],canCreate:$actor->can('create',DataImportBatch::class)); }
}
