@php
    $draftModels = $chatModels ?? [];
    $draftType = old('type', isset($prompt) ? (string) $prompt->type : 'content');
@endphp

<section
    data-prompt-draft
    data-draft-url="{{ route('admin.ai-prompts.drafts') }}"
    data-rewrite-url="{{ route('admin.ai-prompts.drafts.ai') }}"
    data-confirm="{{ __('admin.ai_prompts.draft.confirm_overwrite') }}"
    data-filled="{{ __('admin.ai_prompts.draft.filled') }}"
    data-failed="{{ __('admin.ai_prompts.draft.failed') }}"
    data-working="{{ __('admin.ai_prompts.draft.working') }}"
    @if ($draftType !== 'content') hidden @endif
    class="border-b border-gray-200 px-5 py-5 sm:px-6"
    aria-labelledby="prompt-draft-title"
>
    <div class="flex items-start gap-3">
        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-700">
            <i data-lucide="sparkles" class="h-4 w-4"></i>
        </span>
        <div class="min-w-0">
            <h2 id="prompt-draft-title" class="text-sm font-semibold text-gray-900">{{ __('admin.ai_prompts.draft.title') }}</h2>
            <p class="mt-1 text-pretty text-sm leading-6 text-gray-600">{{ __('admin.ai_prompts.draft.subtitle') }}</p>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="lg:col-span-2">
            <label for="prompt_draft_scenario" class="block text-sm font-semibold text-gray-800">{{ __('admin.ai_prompts.draft.scenario') }}</label>
            <select id="prompt_draft_scenario" data-draft-scenario class="mt-2 block min-h-11 w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-green-500 focus:ring-green-500">
                <option value="service_trust">{{ __('admin.ai_prompts.draft.scenarios.service_trust') }}</option>
                <option value="solution_match">{{ __('admin.ai_prompts.draft.scenarios.solution_match') }}</option>
                <option value="scenario_solution">{{ __('admin.ai_prompts.draft.scenarios.scenario_solution') }}</option>
                <option value="decision_faq">{{ __('admin.ai_prompts.draft.scenarios.decision_faq') }}</option>
            </select>
        </div>
        <div>
            <label for="prompt_draft_brand" class="block text-sm font-semibold text-gray-800">{{ __('admin.ai_prompts.draft.brand') }}</label>
            <input id="prompt_draft_brand" data-draft-brand type="text" maxlength="100" class="mt-2 block min-h-11 w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-green-500 focus:ring-green-500" placeholder="{{ __('admin.ai_prompts.draft.brand_placeholder') }}">
        </div>
        <div>
            <label for="prompt_draft_audience" class="block text-sm font-semibold text-gray-800">{{ __('admin.ai_prompts.draft.audience') }}</label>
            <input id="prompt_draft_audience" data-draft-audience type="text" maxlength="200" class="mt-2 block min-h-11 w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-green-500 focus:ring-green-500" placeholder="{{ __('admin.ai_prompts.draft.audience_placeholder') }}">
        </div>
        <div class="lg:col-span-2">
            <label for="prompt_draft_services" class="block text-sm font-semibold text-gray-800">{{ __('admin.ai_prompts.draft.services') }}</label>
            <textarea id="prompt_draft_services" data-draft-services rows="2" maxlength="500" class="mt-2 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm leading-6 text-gray-900 shadow-sm focus:border-green-500 focus:ring-green-500" placeholder="{{ __('admin.ai_prompts.draft.services_placeholder') }}"></textarea>
        </div>
        <div class="lg:col-span-2">
            <label for="prompt_draft_notes" class="block text-sm font-semibold text-gray-800">{{ __('admin.ai_prompts.draft.notes') }}</label>
            <textarea id="prompt_draft_notes" data-draft-notes rows="2" maxlength="500" class="mt-2 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm leading-6 text-gray-900 shadow-sm focus:border-green-500 focus:ring-green-500" placeholder="{{ __('admin.ai_prompts.draft.notes_placeholder') }}"></textarea>
        </div>
        @if ($draftModels !== [])
            <div class="lg:col-span-2">
                <label for="prompt_draft_model" class="block text-sm font-semibold text-gray-800">{{ __('admin.ai_prompts.draft.model') }}</label>
                <select id="prompt_draft_model" data-draft-model class="mt-2 block min-h-11 w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-green-500 focus:ring-green-500">
                    @foreach ($draftModels as $model)
                        <option value="{{ $model['id'] }}">{{ $model['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <button type="button" data-draft-generate class="inline-flex min-h-10 items-center justify-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-[background-color,transform] duration-150 hover:bg-green-700 active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-500 focus-visible:ring-offset-2">
            {{ __('admin.ai_prompts.draft.generate') }}
        </button>
        @if ($draftModels !== [])
            <button type="button" data-draft-rewrite class="inline-flex min-h-10 items-center justify-center rounded-lg border border-green-600 bg-white px-4 py-2 text-sm font-semibold text-green-700 transition-[background-color,transform] duration-150 hover:bg-green-50 active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-500 focus-visible:ring-offset-2">
                {{ __('admin.ai_prompts.draft.rewrite') }}
            </button>
        @else
            <p class="text-sm leading-6 text-gray-500">{{ __('admin.ai_prompts.draft.model_empty') }}</p>
        @endif
    </div>
    <p data-draft-status class="mt-3 text-sm leading-6 text-gray-600" role="status" aria-live="polite"></p>
</section>
