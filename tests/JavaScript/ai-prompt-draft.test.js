import test from 'node:test';
import assert from 'node:assert/strict';
import { shouldConfirmOverwrite, readPromptDraftFields, applyPromptDraft } from '../../resources/js/admin/ai-prompt-draft.js';

test('an empty editor can be filled without confirmation', () => {
    assert.equal(shouldConfirmOverwrite('', '   '), false);
    assert.equal(shouldConfirmOverwrite('已有名称', ''), true);
    assert.equal(shouldConfirmOverwrite('', '已有正文'), true);
});

test('draft fields are read from the panel and applied only to the editor controls', () => {
    const panel = {
        querySelector(selector) {
            return {
                '[data-draft-scenario]': { value: 'service_trust' },
                '[data-draft-brand]': { value: '示例物流' },
                '[data-draft-services]': { value: '干线' },
                '[data-draft-audience]': { value: '货主' },
                '[data-draft-notes]': { value: '' },
                '[data-draft-model]': { value: '7' },
            }[selector];
        },
    };
    assert.deepEqual(readPromptDraftFields(panel), {
        scenario: 'service_trust',
        brand: '示例物流',
        services: '干线',
        audience: '货主',
        notes: '',
        model_id: '7',
    });

    const name = { value: '旧名称' };
    const content = { value: '旧正文' };
    applyPromptDraft(name, content, { name: '新名称', content: '新正文' });
    assert.equal(name.value, '新名称');
    assert.equal(content.value, '新正文');
});
