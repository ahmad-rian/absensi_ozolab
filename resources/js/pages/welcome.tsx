import { Head } from '@inertiajs/react';
import '../../css/depan.css';
import { Alur } from '@/components/depan/alur';
import { Fitur } from '@/components/depan/fitur';
import { Hero } from '@/components/depan/hero';
import { Lembaga } from '@/components/depan/lembaga';
import { Penutup } from '@/components/depan/penutup';
import { TanyaJawab } from '@/components/depan/tanya-jawab';
import { Navbar } from '@/components/welcome/navbar';

export default function Welcome() {
    return (
        <div className="depan min-h-screen">
            <Head title="Studio Foto & Absensi Sekolah">
                <link
                    rel="stylesheet"
                    href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,600..800&family=JetBrains+Mono:wght@500&display=swap"
                />
            </Head>
            <Navbar />

            <main>
                <Hero />
                <Alur />
                <Fitur />
                <Lembaga />
                <TanyaJawab />
            </main>

            <Penutup />
        </div>
    );
}
