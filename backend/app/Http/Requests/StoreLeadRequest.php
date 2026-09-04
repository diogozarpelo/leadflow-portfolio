<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for every lead type.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = (string) $this->input('type');

        return [
            'type' => [
                'required',
                'string',
                Rule::in([
                    Lead::TYPE_CONTACT,
                    Lead::TYPE_SAMPLE_REQUEST,
                    Lead::TYPE_WHATSAPP,
                ]),
            ],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'company' => ['required', 'string', 'max:180'],
            'company_registration' => [
                Rule::requiredIf(in_array(
                    $type,
                    [Lead::TYPE_SAMPLE_REQUEST, Lead::TYPE_WHATSAPP],
                    true
                )),
                'nullable',
                'string',
                'max:80',
            ],
            'phone' => ['required', 'string', 'max:50'],
            'sector' => [
                Rule::requiredIf($type === Lead::TYPE_CONTACT),
                'nullable',
                'string',
                'max:100',
            ],
            'location' => [
                Rule::requiredIf($type === Lead::TYPE_SAMPLE_REQUEST),
                'nullable',
                'string',
                'max:180',
            ],
            'quantity' => [
                Rule::requiredIf($type === Lead::TYPE_WHATSAPP),
                'nullable',
                'string',
                'max:100',
            ],
            'message' => [
                Rule::requiredIf(in_array(
                    $type,
                    [Lead::TYPE_CONTACT, Lead::TYPE_SAMPLE_REQUEST],
                    true
                )),
                'nullable',
                'string',
                'max:5000',
            ],
            'language' => [
                'required',
                'string',
                Rule::in(['pt', 'en', 'es', 'zh', 'ar']),
            ],
            'source_page' => [
                'required',
                'string',
                Rule::in(['home', 'blog']),
            ],
            'website' => ['prohibited'],
        ];
    }
}
