export function cleanupSessionHistory(state, dialog, closeDialog) {
    state.historyController?.abort();
    state.historyController = null;
    state.open = false;
    if (closeDialog && dialog.open) dialog.close();
}

export function createSessionActivationScheduler({ startEpoch, onChange, now = Date.now, setTimeout = globalThis.setTimeout, clearTimeout = globalThis.clearTimeout, documentTarget = document, windowTarget = window }) {
    let timer = null;

    const update = () => {
        clearTimeout(timer);
        timer = null;
        const active = now() >= startEpoch;
        onChange(active);
        if (!active) timer = setTimeout(update, Math.max(0, startEpoch - now()));
    };
    const onVisibility = () => {
        if (!documentTarget.hidden) update();
    };

    documentTarget.addEventListener('visibilitychange', onVisibility);
    windowTarget.addEventListener('focus', update);
    update();

    return {
        destroy() {
            clearTimeout(timer);
            timer = null;
            documentTarget.removeEventListener('visibilitychange', onVisibility);
            windowTarget.removeEventListener('focus', update);
        },
    };
}

export function guruSessionActivation({ startEpoch, timeLabel }) {
    return {
        isDisabled: true,
        loading: false,
        feedback: '',
        errorFeedback: '',
        timeLabel,
        scheduler: null,
        init() {
            this.scheduler = createSessionActivationScheduler({
                startEpoch,
                onChange: (active) => {
                    this.isDisabled = !active;
                    this.timeLabel = active ? '' : timeLabel;
                },
            });
        },
        destroy() {
            this.scheduler?.destroy();
        },
    };
}

export function startMinuteClock({ render, now = () => new Date(), setTimeout = globalThis.setTimeout, clearTimeout = globalThis.clearTimeout }) {
    let timer = null;
    const tick = () => {
        const current = now();
        render(`${String(current.getHours()).padStart(2, '0')}:${String(current.getMinutes()).padStart(2, '0')}`);
        timer = setTimeout(tick, 60000 - (current.getSeconds() * 1000 + current.getMilliseconds()));
    };
    tick();
    return () => clearTimeout(timer);
}
