/**
 * Sound notification utility for Ocean Sport.
 * Manages AudioContext lifecycle in compliance with browser autoplay policy.
 */

let globalAudioCtx = null;
let isAudioInitialized = false;

export const initAudioContext = () => {
    if (typeof window === 'undefined') return;

    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;

        if (!globalAudioCtx) {
            globalAudioCtx = new AudioCtx();
        }

        if (globalAudioCtx.state === 'suspended') {
            globalAudioCtx.resume().catch(() => {});
        }

        isAudioInitialized = true;
    } catch (e) {
        // Silently ignore browser audio permission restrictions
    }
};

// Register user gesture listeners to resume AudioContext cleanly on first interaction
if (typeof window !== 'undefined') {
    const gestureHandler = () => {
        initAudioContext();
        window.removeEventListener('click', gestureHandler);
        window.removeEventListener('touchstart', gestureHandler);
        window.removeEventListener('keydown', gestureHandler);
    };

    window.addEventListener('click', gestureHandler, { passive: true, once: true });
    window.addEventListener('touchstart', gestureHandler, { passive: true, once: true });
    window.addEventListener('keydown', gestureHandler, { passive: true, once: true });
}

/**
 * Play a subtle chime notification sound (D5 -> A5 -> D6).
 * Safely skips if user has not interacted with the page yet (no unallowed AudioContext warnings).
 */
export const playNotificationSound = () => {
    if (!globalAudioCtx || globalAudioCtx.state !== 'running') {
        // If context exists but suspended, try resume without throwing error
        if (globalAudioCtx && globalAudioCtx.state === 'suspended') {
            globalAudioCtx.resume().then(() => {
                playChime(globalAudioCtx);
            }).catch(() => {});
        }
        return;
    }

    playChime(globalAudioCtx);
};

const playChime = (ctx) => {
    try {
        const now = ctx.currentTime;

        // Chime tone 1: D5 (587.33Hz)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, now);
        gain1.gain.setValueAtTime(0.2, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.35);

        // Chime tone 2: A5 (880Hz)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(880, now + 0.12);
        gain2.gain.setValueAtTime(0.25, now + 0.12);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.12);
        osc2.stop(now + 0.5);
    } catch (e) {
        // Silently catch audio playback errors
    }
};
