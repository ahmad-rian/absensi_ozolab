import { useEffect, useRef } from 'react';

type Pemutar = {
    play: () => void;
    pause: () => void;
    goToAndStop: (nilai: number, isFrame?: boolean) => void;
    destroy: () => void;
};

/**
 * Animasi Lottie dari `public/lottie/<nama>.json`.
 *
 * Ringan karena tiga hal: pemutarnya build `lottie_light` (SVG saja, tanpa
 * ekspresi/eval) yang baru diunduh saat animasi pertama mendekati layar,
 * JSON-nya diambil terpisah dan di-cache browser, dan animasi berhenti
 * diputar begitu keluar layar. Pengguna "kurangi gerakan" mendapat bingkai
 * akhir yang diam.
 *
 * Berkas JSON boleh diganti hasil Lottie Creator selama namanya sama.
 */
export function AnimasiLottie({
    nama,
    className = '',
}: {
    nama: 'scan' | 'pesan' | 'laporan';
    className?: string;
}) {
    const wadah = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const el = wadah.current;

        if (!el || typeof IntersectionObserver === 'undefined') {
            return;
        }

        let pemutar: Pemutar | null = null;
        let batal = false;
        const diam = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        const muat = async (): Promise<void> => {
            const [{ default: lottie }, respons] = await Promise.all([
                import('lottie-web/build/player/lottie_light'),
                fetch(`/lottie/${nama}.json`),
            ]);

            if (batal || !respons.ok) {
                return;
            }

            pemutar = lottie.loadAnimation({
                container: el,
                renderer: 'svg',
                loop: !diam,
                autoplay: !diam,
                animationData: await respons.json(),
                rendererSettings: { preserveAspectRatio: 'xMidYMid meet' },
            }) as Pemutar;

            if (diam) {
                pemutar.goToAndStop(100, true);
            }
        };

        const pengamat = new IntersectionObserver(
            ([entri]) => {
                if (!entri.isIntersecting) {
                    pemutar?.pause();

                    return;
                }

                if (!pemutar) {
                    // Animasi hiasan; gagal memuat cukup meninggalkan ruang kosong.
                    muat().catch(() => {});
                } else if (!diam) {
                    pemutar.play();
                }
            },
            { rootMargin: '200px 0px' },
        );
        // Amati pembungkus `.muncul-cetak` kalau ada: selama belum tersingkap
        // anak-anaknya terpotong clip-path, dan elemen yang terpotong penuh
        // tidak pernah dianggap terlihat oleh IntersectionObserver.
        pengamat.observe(el.closest('.muncul-cetak') ?? el);

        return () => {
            batal = true;
            pengamat.disconnect();
            pemutar?.destroy();
        };
    }, [nama]);

    return <div ref={wadah} aria-hidden="true" className={className} />;
}
