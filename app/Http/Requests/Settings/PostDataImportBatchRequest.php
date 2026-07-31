<?php

namespace App\Http\Requests\Settings;

use App\Models\DataImportBatch;
use Illuminate\Foundation\Http\FormRequest;

class PostDataImportBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DataImportBatch|null $dataImportBatch */
        $dataImportBatch = $this->route('dataImportBatch');

        return $dataImportBatch instanceof DataImportBatch
            && $this->user()?->can('post', $dataImportBatch) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
