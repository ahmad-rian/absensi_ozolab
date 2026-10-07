import '../../css/depan.css';
import { Fitur } from '@/components/depan/fitur';
import { FontDepan } from '@/components/depan/font-depan';
import { Hero } from '@/components/depan/hero';
import { Pengguna } from '@/components/depan/pengguna';
import { Penutup } from '@/components/depan/penutup';
import { Ragam } from '@/components/depan/ragam';
import { SatuKartu } from '@/components/depan/satu-kartu';
import { TanyaJawab } from '@/components/depan/tanya-jawab';
import { Navbar } from '@/components/welcome/navbar';

export default function Welcome() {
    return (
        <div className="depan min-h-screen">
            <FontDepan judul="Studio Foto & Absensi Sekolah" />
            <Navbar />

            <main>
                <Hero />
                <Ragam />
                <SatuKartu />
                <Pengguna />
                <Fitur />
                <TanyaJawab />
            </main>

            <Penutup />
        </div>
    );
}
