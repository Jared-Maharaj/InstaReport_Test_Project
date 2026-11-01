<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaReportIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'severity' => ['nullable', Rule::in(['HIGH', 'MEDIUM', 'LOW'])],
            'status' => ['nullable', Rule::in(['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'])],
            'reference' => ['nullable', 'string', 'max:40', 'regex:/^[a-zA-Z0-9-]+$/'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}