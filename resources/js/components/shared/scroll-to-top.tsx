import { ArrowUp } from 'lucide-react';
import { useEffect, useState } from 'react';

export function ScrollToTop() {
    const [show, setShow] = useState(false);

    useEffect(() => {
        const onScroll = () => setShow(window.scrollY > 400);
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    if (!show) return null;

    return (
        <button
            onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
            className="fixed right-6 bottom-6 z-50 flex size-11 items-center justify-center rounded-full border bg-white shadow-lg transition-all hover:bg-zinc-50 animate-fade-up dark:border-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700"
            aria-label="Scroll to top"
        >
            <ArrowUp className="size-4" />
        </button>
    );
}
