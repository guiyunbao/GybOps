/**
 * @param {string} name
 * @param {string} content
 * @returns {boolean}
 */
export function shouldConfirmOverwrite(name, content) {
    return name.trim() !== '' || content.trim() !== '';
}

/**
 * @param {ParentNode} root
 * @returns {{scenario: string, brand: string, services: string, audience: string, notes: string, model_id: string}}
 */
export function readPromptDraftFields(root) {
    const value = (selector) => root.querySelector(selector)?.value ?? '';

    return {
        scenario: value('[data-draft-scenario]'),
        brand: value('[data-draft-brand]'),
        services: value('[data-draft-services]'),
        audience: value('[data-draft-audience]'),
        notes: value('[data-draft-notes]'),
        model_id: value('[data-draft-model]'),
    };
}

/**
 * 把生成结果写入提示词编辑框，不提交保存表单。
 *
 * @param {HTMLInputElement | null} nameInput
 * @param {HTMLTextAreaElement | null} contentInput
 * @param {{name?: string, content?: string}} draft
 */
export function applyPromptDraft(nameInput, contentInput, draft) {
    if (nameInput) nameInput.value = draft.name ?? '';
    if (contentInput) contentInput.value = draft.content ?? '';
}

/**
 * @param {HTMLElement} root
 */
export function initializePromptDraft(root) {
    const typeSelect = document.getElementById('prompt_type');
    const nameInput = document.getElementById('prompt_name');
    const contentInput = document.getElementById('prompt_content');
    const status = root.querySelector('[data-draft-status]');
    const generateButton = root.querySelector('[data-draft-generate]');
    const rewriteButton = root.querySelector('[data-draft-rewrite]');
    let pending = false;

    const syncVisibility = () => {
        if (typeSelect instanceof HTMLSelectElement) {
            root.hidden = typeSelect.value !== 'content';
        }
    };

    const setStatus = (message) => {
        if (status) status.textContent = message;
    };

    const setPending = (isPending) => {
        pending = isPending;
        for (const button of [generateButton, rewriteButton]) {
            if (button instanceof HTMLButtonElement) button.disabled = isPending;
        }
        setStatus(isPending ? root.dataset.working || '' : '');
    };

    /**
     * @param {string} url
     * @param {Record<string, string>} payload
     */
    const requestDraft = async (url, payload) => {
        if (pending || root.hidden) return;
        const name = nameInput instanceof HTMLInputElement ? nameInput.value : '';
        const content = contentInput instanceof HTMLTextAreaElement ? contentInput.value : '';
        if (shouldConfirmOverwrite(name, content) && !window.confirm(root.dataset.confirm || '')) return;

        setPending(true);
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || typeof data.content !== 'string' || typeof data.name !== 'string') {
                setStatus(data.message || root.dataset.failed || '');
                return;
            }
            applyPromptDraft(
                nameInput instanceof HTMLInputElement ? nameInput : null,
                contentInput instanceof HTMLTextAreaElement ? contentInput : null,
                data,
            );
            setStatus(root.dataset.filled || '');
        } catch {
            setStatus(root.dataset.failed || '');
        } finally {
            pending = false;
            for (const button of [generateButton, rewriteButton]) {
                if (button instanceof HTMLButtonElement) button.disabled = false;
            }
        }
    };

    root.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target instanceof HTMLInputElement) {
            event.preventDefault();
        }
    });
    generateButton?.addEventListener('click', () => {
        const fields = readPromptDraftFields(root);
        void requestDraft(root.dataset.draftUrl || '', {
            scenario: fields.scenario,
            brand: fields.brand,
            services: fields.services,
            audience: fields.audience,
            notes: fields.notes,
        });
    });
    rewriteButton?.addEventListener('click', () => {
        const fields = readPromptDraftFields(root);
        void requestDraft(root.dataset.rewriteUrl || '', {
            scenario: fields.scenario,
            brand: fields.brand,
            services: fields.services,
            audience: fields.audience,
            notes: fields.notes,
            model_id: fields.model_id,
        });
    });
    typeSelect?.addEventListener('change', syncVisibility);
    syncVisibility();
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-prompt-draft]').forEach((root) => {
        if (root instanceof HTMLElement) initializePromptDraft(root);
    });
}
