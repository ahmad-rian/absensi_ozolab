// Web Audio API sound generator — no external audio files needed.
// Lazy-init AudioContext to avoid browser autoplay policy issues.
let audioCtx: AudioContext | null = null;

function getContext(): AudioContext | null {
    if (typeof window === 'undefined') return null;
    if (!audioCtx) {
        try {
            audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
        } catch {
            return null;
        }
    }
    if (audioCtx.state === 'suspended') {
        audioCtx.resume().catch(() => {});
    }
    return audioCtx;
}

function playTone(frequency: number, duration: number, type: OscillatorType = 'sine', volume = 0.3) {
    const ctx = getContext();
    if (!ctx) return;

    try {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = type;
        osc.frequency.value = frequency;
        gain.gain.value = volume;
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + duration);
    } catch {
        // Silently fail — sound is not critical
    }
}

export function playSuccessSound() {
    playTone(880, 0.15, 'sine', 0.25);
    setTimeout(() => playTone(1320, 0.2, 'sine', 0.25), 120);
}

export function playErrorSound() {
    playTone(220, 0.3, 'square', 0.15);
    setTimeout(() => playTone(180, 0.3, 'square', 0.15), 200);
}
