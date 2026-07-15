import { useEffect } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { getLenis } from '@/hooks/use-lenis';

gsap.registerPlugin(ScrollTrigger);

/**
 * Bridges the shared Lenis smooth-scroll instance with GSAP's ScrollTrigger.
 *
 * Lenis hijacks the native scroll position, so ScrollTrigger must be told to
 * recompute on every Lenis tick — otherwise scroll-driven animations lag or
 * freeze. Wiring stays minimal: a single `scroll` subscription that calls
 * `ScrollTrigger.update`, cleaned up on unmount. If Lenis is not present the
 * hook is a no-op and native scrolling still drives ScrollTrigger normally.
 */
export function useGsapScrollSync(): void {
    useEffect(() => {
        const lenis = getLenis();
        if (!lenis) {
            return;
        }

        const update = () => ScrollTrigger.update();
        lenis.on('scroll', update);

        return () => {
            lenis.off('scroll', update);
        };
    }, []);
}
