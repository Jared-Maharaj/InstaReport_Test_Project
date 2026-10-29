<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportIncidentRequest extends FormRequest
{
    private const CATEGORIES = [
        'SECURITY' => ['BREAK_IN', 'THEFT', 'PHYSICAL_ATTACK', 'OTHER'],
        'SERVICE_DELIVERY' => ['POTHOLE', 'ELECTRICITY_OUTAGE', 'WATER_OUTAGE', 'OTHER'],
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $type = $this->input('type');
        $categories = is_string($type) ? (self::CATEGORIES[$type] ?? []) : [];

        return [
            'type' => ['bail', 'required', 'string', Rule::in(array_keys(self::CATEGORIES))],
            'category' => ['bail', 'required', 'string', 'max:50', Rule::in($categories)],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'severity' => ['required_if:type,SECURITY', 'prohibited_unless:type,SECURITY', 'nullable', Rule::in(['HIGH', 'MEDIUM', 'LOW'])],
            'address' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'occurred_at' => ['bail', 'required', 'date_format:Y-m-d\TH:i:sP,Y-m-d\TH:i:s\Z,Y-m-d\TH:i:s.vP,Y-m-d\TH:i:s.v\Z', 'before_or_equal:now'],
            'photos' => ['sometimes', 'array', 'list', 'max:5'],
            'photos.*' => ['bail', 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'id' => ['missing'],
            'reporter_id' => ['missing'],
            'reference' => ['missing'],
            'status' => ['missing'],
            'created_at' => ['missing'],
            'updated_at' => ['missing'],
        ];
    }
}