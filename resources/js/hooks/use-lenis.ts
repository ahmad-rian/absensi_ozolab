import { useEffect } from 'react';
import Lenis from 'lenis';

/**
 * Shared Lenis instance for the mounted landing page.
 *
 * Exposed as a module-level singleton so GSAP's ScrollTrigger (in the hero)
 * can subscribe to Lenis scroll events without threading the instance through
 * React context. It is set on mount and cleared on unmount.
 */
let activeLenis: Lenis | null = null;

export function getLenis(): Lenis | null {
    return activeLenis;
}

/**
 * Initializes Lenis smooth scrolling for the duration of the mounting component.
 *
 * Runs a requestAnimationFrame loop and tears everything down on unmount so the
 * effect stays scoped to the page that mounts it (e.g. the public landing page)
 * and never leaks into admin/auth screens.
 */
export function useLenis(): void {
    useEffect(() => {
        const lenis = new Lenis();
        activeLenis = lenis;

        let frameId = requestAnimationFrame(function raf(time: number) {
            lenis.raf(time);
            frameId = requestAnimationFrame(raf);
        });

        return () => {
            cancelAnimationFrame(frameId);
            lenis.destroy();
            if (activeLenis === lenis) {
                activeLenis = null;
            }
        };
    }, []);
}
