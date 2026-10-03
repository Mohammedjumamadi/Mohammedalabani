<?php

namespace App\Http\Requests;

use App\Enums\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'media_type' => ['required', Rule::enum(MediaType::class)],
            // فحص النوع والحجم والمحتوى يتم في FileValidator بعد وصول الملف
            'file' => ['required', 'file'],
            'consent' => ['accepted'],
            'reanalyze' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => __('upload.errors.consent'),
            'file.required' => __('upload.errors.required'),
        ];
    }
}
