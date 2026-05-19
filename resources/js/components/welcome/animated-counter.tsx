// Lightweight counter — uses requestAnimationFrame instead of framer-motion.
import { useEffect, useRef, useState } from 'react';

interface AnimatedCounterProps {
    target: number;
    suffix?: string;
    duration?: number;
    decimals?: number;
}

export function AnimatedCounter({ target, suffix = '', duration = 1500, decimals = 0 }: AnimatedCounterProps) {
    const ref = useRef<HTMLSpanElement>(null);
    const [started, setStarted] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setStarted(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.3 },
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (!started || !ref.current) return;

        const start = performance.now();
        const el = ref.current;

        function tick(now: number) {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
            const value = eased * target;
            el.textContent = (decimals > 0 ? value.toFixed(decimals) : Math.round(value).toLocaleString('id-ID')) + suffix;
            if (progress < 1) requestAnimationFrame(tick);
        }

        requestAnimationFrame(tick);
    }, [started, target, suffix, duration, decimals]);

    return <span ref={ref} style={{ fontVariantNumeric: 'tabular-nums' }}>0{suffix}</span>;
}
