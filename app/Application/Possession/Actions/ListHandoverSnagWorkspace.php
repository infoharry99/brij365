<?php
namespace App\Application\Possession\Actions;
use App\Application\Possession\Data\HandoverSnagWorkspaceData; use App\Domain\Possession\Services\PossessionRegister; use App\Models\HandoverSnag; use App\Models\User;
final class ListHandoverSnagWorkspace { public function __construct(private readonly PossessionRegister $register) {} public function execute(User $actor,array $filters): HandoverSnagWorkspaceData { return new HandoverSnagWorkspaceData($this->register->snags($actor,$filters),$filters,$this->register->openHandovers($actor),['open'=>'Open','resolved'=>'Resolved'],['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'],['canReportSnag'=>$actor->can('create',HandoverSnag::class)]); } }
