import { Head } from '@inertiajs/react';

/** Font halaman publik bergaya kolase: Instrument Serif untuk judul, Hanken Grotesk untuk teks. */
export function FontDepan({ judul }: { judul: string }) {
    return (
        <Head title={judul}>
            <link
                rel="stylesheet"
                href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Hanken+Grotesk:wght@400;500;600;700&display=swap"
            />
        </Head>
    );
}
