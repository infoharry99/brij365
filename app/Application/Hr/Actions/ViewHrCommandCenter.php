<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandCenterData;
use App\Domain\Hr\Services\HrCommandCenterRegister;
use App\Models\User;

final class ViewHrCommandCenter
{
    public function __construct(private readonly HrCommandCenterRegister $register) {}

    public function execute(User $actor): HrCommandCenterData
    {
        return $this->register->read($actor);
    }
}
