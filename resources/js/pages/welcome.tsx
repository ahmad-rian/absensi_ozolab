import { Head } from '@inertiajs/react';
import '../../css/depan.css';
import { Fitur } from '@/components/depan/fitur';
import { Hero } from '@/components/depan/hero';
import { Pengguna } from '@/components/depan/pengguna';
import { Penutup } from '@/components/depan/penutup';
import { Ragam } from '@/components/depan/ragam';
import { TanyaJawab } from '@/components/depan/tanya-jawab';
import { Navbar } from '@/components/welcome/navbar';

export default function Welcome() {
    return (
        <div className="depan min-h-screen">
            <Head title="Studio Foto & Absensi Sekolah">
                <link
                    rel="stylesheet"
                    href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Hanken+Grotesk:wght@400;500;600;700&display=swap"
                />
            </Head>
            <Navbar />

            <main>
                <Hero />
                <Ragam />
                <Pengguna />
                <Fitur />
                <TanyaJawab />
            </main>

            <Penutup />
        </div>
    );
}
