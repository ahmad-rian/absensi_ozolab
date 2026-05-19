import { Accordion, AccordionItem, AccordionTrigger, AccordionContent } from '@/components/ui/accordion';

interface FaqItem {
    question: string;
    answer: string;
}

const faqs: FaqItem[] = [
    {
        question: 'Apakah sistem ini gratis?',
        answer: 'Kami menyediakan paket gratis untuk sekolah dengan maksimal 100 siswa, termasuk fitur dasar seperti scan QR dan rekap kehadiran. Untuk fitur lengkap seperti notifikasi WhatsApp dan laporan lanjutan, tersedia paket berbayar dengan harga terjangkau.',
    },
    {
        question: 'Bagaimana cara mendaftarkan sekolah?',
        answer: 'Cukup klik tombol "Daftar Sekarang", isi data sekolah dan admin utama, lalu verifikasi email. Dalam 5 menit sekolah Anda sudah bisa mulai menggunakan sistem absensi digital. Tim kami juga siap membantu proses onboarding.',
    },
    {
        question: 'Apakah perlu hardware khusus untuk scan QR?',
        answer: 'Tidak perlu. Cukup gunakan smartphone atau tablet dengan kamera untuk memindai QR Code. Sistem kami berbasis web sehingga bisa diakses dari browser mana saja tanpa instalasi aplikasi khusus.',
    },
    {
        question: 'Berapa biaya pengiriman notifikasi WhatsApp?',
        answer: 'Biaya notifikasi WhatsApp sudah termasuk dalam paket berlangganan, tanpa biaya tambahan per pesan. Kami menggunakan WhatsApp Business API resmi untuk memastikan pengiriman yang andal dan cepat ke semua nomor orang tua.',
    },
    {
        question: 'Bagaimana keamanan data siswa dijaga?',
        answer: 'Data disimpan dengan enkripsi end-to-end pada server yang berlokasi di Indonesia. Kami mematuhi regulasi perlindungan data pribadi dan hanya pihak sekolah yang berwenang yang dapat mengakses informasi siswa.',
    },
    {
        question: 'Bisakah digunakan offline?',
        answer: 'Fitur scan QR memerlukan koneksi internet untuk mencatat data secara real-time. Namun, data yang sudah tercatat dapat diakses offline melalui fitur ekspor. Kami juga sedang mengembangkan mode offline penuh untuk area dengan koneksi terbatas.',
    },
];

const leftColumn = faqs.slice(0, 3);
const rightColumn = faqs.slice(3);

export function FaqSection() {
    return (
        <section id="faq" className="bg-slate-50/80 py-24 sm:py-32 lg:py-40 dark:bg-slate-900/40">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="text-center animate-fade-up">
                    <h2 className="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">
                        Pertanyaan yang Sering Diajukan
                    </h2>
                    <p className="text-muted-foreground mx-auto mt-4 max-w-2xl text-lg">
                        Temukan jawaban untuk pertanyaan umum seputar sistem absensi digital kami.
                    </p>
                </div>

                <div className="mt-12 grid gap-x-12 gap-y-0 md:grid-cols-2">
                    <div>
                        <Accordion type="single" collapsible>
                            {leftColumn.map((faq, index) => (
                                <div key={index}>
                                    <AccordionItem value={`left-${index}`}>
                                        <AccordionTrigger className="text-left text-sm font-semibold">
                                            {faq.question}
                                        </AccordionTrigger>
                                        <AccordionContent>
                                            <p className="text-muted-foreground leading-relaxed">
                                                {faq.answer}
                                            </p>
                                        </AccordionContent>
                                    </AccordionItem>
                                </div>
                            ))}
                        </Accordion>
                    </div>
                    <div>
                        <Accordion type="single" collapsible>
                            {rightColumn.map((faq, index) => (
                                <div key={index}>
                                    <AccordionItem value={`right-${index}`}>
                                        <AccordionTrigger className="text-left text-sm font-semibold">
                                            {faq.question}
                                        </AccordionTrigger>
                                        <AccordionContent>
                                            <p className="text-muted-foreground leading-relaxed">
                                                {faq.answer}
                                            </p>
                                        </AccordionContent>
                                    </AccordionItem>
                                </div>
                            ))}
                        </Accordion>
                    </div>
                </div>
            </div>
        </section>
    );
}
