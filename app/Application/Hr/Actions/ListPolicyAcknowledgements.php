<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\PolicyAcknowledgementRegisterData;
use App\Domain\Hr\Services\PolicyAcknowledgementRegister;
use App\Models\User;

final class ListPolicyAcknowledgements
{
    public function __construct(private readonly PolicyAcknowledgementRegister $register) {}

    public function execute(User $actor, array $filters): PolicyAcknowledgementRegisterData
    {
        return $this->register->all($actor, $filters);
    }
}
