/**
 * 后台分步聚光灯引导。
 *
 * 从 #gf-page-guide 读取当前页步骤，高亮 data-gf-guide 标记，并在完成或跳过时写回进度。
 */

const NARROW_WIDTH = 720;

/**
 * @typedef {object} PageGuideStep
 * @property {string} target
 * @property {string} title
 * @property {string} body
 * @property {string} placement
 */

/**
 * @typedef {object} PageGuidePayload
 * @property {string} id
 * @property {boolean} autoOpen
 * @property {string} completeUrl
 * @property {{next: string, back: string, skip: string, done: string, progress: string}} labels
 * @property {PageGuideStep[]} steps
 */

/**
 * 读取当前页引导数据。
 *
 * @returns {PageGuidePayload|null}
 */
function readPayload() {
    const node = document.getElementById('gf-page-guide');
    if (!(node instanceof HTMLElement)) {
        return null;
    }

    try {
        const payload = JSON.parse(node.textContent || '');
        if (!payload || typeof payload.id !== 'string' || !Array.isArray(payload.steps)) {
            return null;
        }

        return payload;
    } catch {
        return null;
    }
}

/**
 * 欢迎弹窗仍打开时，本页引导先等待，避免两层遮罩叠在一起。
 *
 * @returns {boolean}
 */
function welcomeIsOpen() {
    const modal = document.getElementById('admin-welcome-modal');

    return modal instanceof HTMLElement && !modal.classList.contains('hidden');
}

/**
 * @returns {Promise<void>}
 */
function waitForWelcome() {
    if (!welcomeIsOpen()) {
        return Promise.resolve();
    }

    const modal = document.getElementById('admin-welcome-modal');

    return new Promise((resolve) => {
        const observer = new MutationObserver(() => {
            if (!welcomeIsOpen()) {
                observer.disconnect();
                resolve();
            }
        });
        observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
    });
}

/**
 * 只保留页面上确实存在、并且有尺寸的步骤。
 *
 * @param {PageGuideStep[]} steps
 * @returns {Array<PageGuideStep & {element: HTMLElement}>}
 */
function availableSteps(steps) {
    return steps.flatMap((step) => {
        if (typeof step.target !== 'string' || step.target === '') {
            return [];
        }

        const element = document.querySelector(step.target);
        if (!(element instanceof HTMLElement)) {
            return [];
        }

        const rect = element.getBoundingClientRect();
        if (rect.width < 1 || rect.height < 1) {
            return [];
        }

        return [{ ...step, element }];
    });
}

/**
 * @param {PageGuidePayload} payload
 */
function mount(payload) {
    const root = document.createElement('div');
    root.className = 'gf-page-guide';
    root.hidden = true;
    root.innerHTML = `
        <div class="gf-page-guide__shade" aria-hidden="true"></div>
        <div class="gf-page-guide__card" role="dialog" aria-modal="false">
            <p class="gf-page-guide__progress"></p>
            <h2></h2>
            <p class="gf-page-guide__body"></p>
            <div class="gf-page-guide__actions">
                <button type="button" class="gf-button gf-button--small" data-gf-guide-back></button>
                <button type="button" class="gf-button gf-button--small" data-gf-guide-skip></button>
                <button type="button" class="gf-button gf-button--primary gf-button--small" data-gf-guide-next></button>
            </div>
        </div>
    `;
    document.body.append(root);

    const shade = root.querySelector('.gf-page-guide__shade');
    const card = root.querySelector('.gf-page-guide__card');
    const progress = root.querySelector('.gf-page-guide__progress');
    const title = root.querySelector('h2');
    const body = root.querySelector('.gf-page-guide__body');
    const backButton = root.querySelector('[data-gf-guide-back]');
    const skipButton = root.querySelector('[data-gf-guide-skip]');
    const nextButton = root.querySelector('[data-gf-guide-next]');
    let steps = [];
    let index = 0;
    let placedFrame = 0;

    backButton.textContent = payload.labels.back;
    skipButton.textContent = payload.labels.skip;

    /**
     * 窄屏时把说明卡固定在底部，避免挡住被高亮的按钮。
     */
    function place() {
        const step = steps[index];
        if (!step || !(shade instanceof HTMLElement) || !(card instanceof HTMLElement)) {
            return;
        }

        const rect = step.element.getBoundingClientRect();
        const pad = 6;
        shade.style.top = `${Math.max(8, rect.top - pad)}px`;
        shade.style.left = `${Math.max(8, rect.left - pad)}px`;
        shade.style.width = `${rect.width + pad * 2}px`;
        shade.style.height = `${rect.height + pad * 2}px`;

        const narrow = window.innerWidth < NARROW_WIDTH;
        root.classList.toggle('is-narrow', narrow);
        if (narrow) {
            card.style.top = 'auto';
            card.style.left = '16px';
            card.style.right = '16px';
            card.style.bottom = '16px';
            return;
        }

        card.style.right = 'auto';
        card.style.bottom = 'auto';
        const width = card.offsetWidth;
        const height = card.offsetHeight;
        let top = rect.bottom + 12;
        let left = rect.left;
        if (step.placement === 'top') {
            top = rect.top - height - 12;
        }
        if (step.placement === 'left') {
            top = rect.top;
            left = rect.left - width - 12;
        }
        if (step.placement === 'right') {
            top = rect.top;
            left = rect.right + 12;
        }
        if (top + height > window.innerHeight - 16) {
            top = Math.max(16, rect.top - height - 12);
        }
        if (left + width > window.innerWidth - 16) {
            left = Math.max(16, window.innerWidth - width - 16);
        }
        card.style.top = `${Math.max(16, top)}px`;
        card.style.left = `${Math.max(16, left)}px`;
    }

    function render() {
        const step = steps[index];
        if (!step) {
            close();
            return;
        }

        progress.textContent = String(payload.labels.progress)
            .replace(':current', String(index + 1))
            .replace(':total', String(steps.length));
        title.textContent = step.title;
        body.textContent = step.body;
        backButton.hidden = index === 0;
        nextButton.textContent = index === steps.length - 1 ? payload.labels.done : payload.labels.next;
        step.element.scrollIntoView({ block: 'center', inline: 'nearest' });
        cancelAnimationFrame(placedFrame);
        placedFrame = requestAnimationFrame(() => requestAnimationFrame(place));
        nextButton.focus();
    }

    function onViewportChange() {
        if (!root.hidden) {
            place();
        }
    }

    /**
     * @param {boolean} persist
     */
    async function finish(persist) {
        close();
        if (!persist) {
            return;
        }

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            await fetch(payload.completeUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ id: payload.id }),
            });
        } catch {
            // 进度写失败时不打断当前操作，下次进入仍会自动打开。
        }
    }

    function close() {
        root.hidden = true;
        window.removeEventListener('resize', onViewportChange);
        window.removeEventListener('scroll', onViewportChange, true);
        document.removeEventListener('keydown', onKeydown);
    }

    /**
     * @param {KeyboardEvent} event
     */
    function onKeydown(event) {
        if (root.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            void finish(true);
        }
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            nextButton.click();
        }
        if (event.key === 'ArrowLeft' && index > 0) {
            event.preventDefault();
            backButton.click();
        }
    }

    /**
     * 打开引导。目标都找不到时不显示遮罩。
     */
    function open() {
        steps = availableSteps(payload.steps);
        if (steps.length === 0) {
            return;
        }

        index = 0;
        root.hidden = false;
        window.addEventListener('resize', onViewportChange);
        window.addEventListener('scroll', onViewportChange, true);
        document.addEventListener('keydown', onKeydown);
        render();
    }

    backButton.addEventListener('click', () => {
        index = Math.max(0, index - 1);
        render();
    });
    skipButton.addEventListener('click', () => {
        void finish(true);
    });
    nextButton.addEventListener('click', () => {
        if (index >= steps.length - 1) {
            void finish(true);
            return;
        }
        index += 1;
        render();
    });

    window.GeoflowPageGuide = { open, close };
}

const payload = readPayload();

if (payload) {
    mount(payload);
    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-gf-guide-replay]')
            : null;
        if (!trigger || !window.GeoflowPageGuide) {
            return;
        }
        void waitForWelcome().then(() => window.GeoflowPageGuide.open());
    });

    if (payload.autoOpen) {
        void waitForWelcome().then(() => window.GeoflowPageGuide.open());
    }
}
