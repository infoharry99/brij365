<?php

namespace App\Http\Requests\Crm;

use App\Models\ProspectInquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseProspectInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProspectInquiry|null $prospectInquiry */
        $prospectInquiry = $this->route('prospectInquiry');

        return $prospectInquiry instanceof ProspectInquiry
            && $this->user()?->can('update', $prospectInquiry) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                ProspectInquiry::STATUS_CLOSED_UNQUALIFIED,
                ProspectInquiry::STATUS_CLOSED_DUPLICATE,
            ])],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
