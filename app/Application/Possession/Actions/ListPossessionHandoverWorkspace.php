<?php
namespace App\Application\Possession\Actions;
use App\Application\Possession\Data\PossessionHandoverWorkspaceData; use App\Domain\Possession\Services\PossessionRegister; use App\Models\PossessionHandover; use App\Models\User;
final class ListPossessionHandoverWorkspace { public function __construct(private readonly PossessionRegister $register) {} public function execute(User $actor,array $filters): PossessionHandoverWorkspaceData { return new PossessionHandoverWorkspaceData($this->register->handovers($actor,$filters),$filters,$this->register->projects($actor),$this->register->eligibleBookings($actor),['ready'=>'Ready','blocked'=>'Blocked','completed'=>'Completed'],['canCreateHandover'=>$actor->can('create',PossessionHandover::class)]); } }
