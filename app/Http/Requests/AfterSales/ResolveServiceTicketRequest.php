<?php

namespace App\Http\Requests\AfterSales;

use App\Models\ServiceTicket;
use Illuminate\Foundation\Http\FormRequest;

class ResolveServiceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $serviceTicket = $this->route('serviceTicket');

        return $serviceTicket instanceof ServiceTicket
            && $this->user()?->can('resolve', $serviceTicket) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution_summary' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
