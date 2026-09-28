export function syncFavicon(document: Document, favicon: string | null): void {
    document.head.querySelectorAll('link[rel="icon"], link[rel="apple-touch-icon"]').forEach((link) => link.remove());

    const icons = favicon
        ? [{ rel: 'icon', href: favicon }, { rel: 'apple-touch-icon', href: favicon }]
        : [{ rel: 'icon', href: '/favicon.ico' }, { rel: 'icon', href: '/favicon.svg' }, { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' }];

    for (const icon of icons) {
        const link = document.createElement('link');
        link.rel = icon.rel;
        link.href = icon.href;
        document.head.appendChild(link);
    }
}
