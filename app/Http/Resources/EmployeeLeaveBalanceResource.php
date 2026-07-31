<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLeaveBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period_year' => $this->period_year,
            'opening_balance_days' => (float) $this->opening_balance_days,
            'accrued_days' => (float) $this->accrued_days,
            'used_days' => (float) $this->used_days,
            'pending_days' => (float) $this->pending_days,
            'adjusted_days' => (float) $this->adjusted_days,
            'available_days' => (float) $this->available_days,
            'ledger' => $this->ledger,
            'employee' => $this->whenLoaded('employee', fn () => [
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'department' => $this->employee->department,
            ]),
            'leave_type' => $this->whenLoaded('leaveType', fn () => [
                'code' => $this->leaveType->code,
                'name' => $this->leaveType->name,
            ]),
        ];
    }
}
