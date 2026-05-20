// Text-to-Speech scan feedback — speaks attendance status aloud.
// Uses Web Speech Synthesis API (built-in, no external files).
// Falls back to tone beeps if speech is unavailable.

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
        // Silent fail
    }
}

function speak(text: string, rate = 1.1) {
    if (typeof window === 'undefined' || !window.speechSynthesis) return;
    try {
        // Cancel any ongoing speech
        window.speechSynthesis.cancel();

        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'id-ID';
        utterance.rate = rate;
        utterance.pitch = 1;
        utterance.volume = 1;

        // Try to find Indonesian voice, fallback to default
        const voices = window.speechSynthesis.getVoices();
        const idVoice = voices.find((v) => v.lang.startsWith('id')) || voices.find((v) => v.lang.startsWith('ms')) || null;
        if (idVoice) utterance.voice = idVoice;

        window.speechSynthesis.speak(utterance);
    } catch {
        // Silent fail — speech not critical
    }
}

// Preload voices (some browsers need this)
if (typeof window !== 'undefined' && window.speechSynthesis) {
    window.speechSynthesis.getVoices();
    window.speechSynthesis.onvoiceschanged = () => {
        window.speechSynthesis.getVoices();
    };
}

export function playSuccessSound(studentName?: string) {
    // Cheerful tone first
    playTone(880, 0.12, 'sine', 0.2);
    setTimeout(() => playTone(1320, 0.15, 'sine', 0.2), 100);

    // Then speak
    const name = studentName || '';
    const message = name ? `${name}, berhasil absen hari ini` : 'Berhasil absen hari ini';
    setTimeout(() => speak(message), 250);
}

export function playErrorSound(reason?: string) {
    // Error buzzer
    playTone(220, 0.25, 'square', 0.12);
    setTimeout(() => playTone(180, 0.25, 'square', 0.12), 150);

    // Then speak error reason
    const message = reason || 'Gagal, coba lagi';
    setTimeout(() => speak(message, 1.0), 300);
}
