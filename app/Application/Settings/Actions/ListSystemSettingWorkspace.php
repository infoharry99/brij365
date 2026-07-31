<?php
namespace App\Application\Settings\Actions;
use App\Application\Settings\Data\SettingsWorkspaceData;
use App\Domain\Settings\Services\SettingsRegister;
use App\Models\SystemSetting;
use App\Models\User;
final class ListSystemSettingWorkspace {
 public function __construct(private readonly SettingsRegister $register) {}
 public function execute(User $actor,array $filters): SettingsWorkspaceData { return new SettingsWorkspaceData('settings',$this->register->settings($actor,$filters),$filters,$this->register->companies($actor),$this->register->groups($actor),$this->register->keys($actor),['draft'=>'Draft','active'=>'Active','archived'=>'Archived'],['json'=>'JSON','object'=>'Object','array'=>'Array','string'=>'String','integer'=>'Integer','decimal'=>'Decimal','boolean'=>'Boolean'],$actor->can('create',SystemSetting::class)); }
}
