<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use App\Support\GeoFlow\LogisticsContentPromptCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentPromptDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') instanceof Admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scenario' => ['required', 'string', Rule::in(LogisticsContentPromptCatalog::KEYS)],
            'brand' => ['nullable', 'string', 'max:100'],
            'services' => ['nullable', 'string', 'max:500'],
            'audience' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
            'model_id' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('admin.ai-prompts.drafts.ai')),
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scenario.required' => __('admin.ai_prompts.draft.error.scenario'),
            'scenario.in' => __('admin.ai_prompts.draft.error.scenario'),
            'model_id.required' => __('admin.ai_prompts.draft.error.model'),
        ];
    }

    /**
     * @return array{brand: string, services: string, audience: string, notes: string}
     */
    public function brief(): array
    {
        return [
            'brand' => trim($this->string('brand')->toString()),
            'services' => trim($this->string('services')->toString()),
            'audience' => trim($this->string('audience')->toString()),
            'notes' => trim($this->string('notes')->toString()),
        ];
    }
}
