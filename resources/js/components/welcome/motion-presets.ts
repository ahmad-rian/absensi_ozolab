// Shared animation constants & variants for the welcome page.
// easeOutExpo gives a premium "snap into place" feel.

export const easeOut = [0.16, 1, 0.3, 1] as const;

export const fadeUp = {
    hidden: { opacity: 0, y: 24 },
    show: (i: number = 0) => ({
        opacity: 1,
        y: 0,
        transition: { delay: i * 0.08, duration: 0.5, ease: easeOut },
    }),
};

export const fadeIn = {
    hidden: { opacity: 0 },
    show: { opacity: 1, transition: { duration: 0.5, ease: easeOut } },
};

export const staggerContainer = {
    hidden: {},
    show: { transition: { staggerChildren: 0.08 } },
};

export const scaleIn = {
    hidden: { opacity: 0, scale: 0.95 },
    show: { opacity: 1, scale: 1, transition: { duration: 0.6, ease: easeOut } },
};

export const slideInLeft = {
    hidden: { opacity: 0, x: -40 },
    show: { opacity: 1, x: 0, transition: { duration: 0.6, ease: easeOut } },
};

export const slideInRight = {
    hidden: { opacity: 0, x: 40 },
    show: { opacity: 1, x: 0, transition: { duration: 0.6, ease: easeOut } },
};

export const viewportOnce = { once: true, margin: '-80px' as const };
