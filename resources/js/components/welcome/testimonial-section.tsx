interface Testimonial {
    initials: string;
    quote: string;
    name: string;
    title: string;
    school: string;
}

const testimonials: Testimonial[] = [
    {
        initials: 'HS',
        quote: 'Sejak menggunakan AbsenKu, rekap kehadiran bulanan yang biasanya butuh 2 hari kini selesai dalam hitungan detik. Orang tua siswa juga lebih tenang karena selalu mendapat notifikasi.',
        name: 'Hadi Santoso',
        title: 'Kepala Sekolah',
        school: 'SD Negeri 4 Surabaya',
    },
    {
        initials: 'RA',
        quote: 'Dulu input absensi manual ke spreadsheet sangat melelahkan. Sekarang semua otomatis dan rapi. Laporan tinggal unduh, tidak perlu format ulang lagi.',
        name: 'Rina Anggraini',
        title: 'Admin Tata Usaha',
        school: 'SMP Islam Al-Hikmah Bandung',
    },
    {
        initials: 'BP',
        quote: 'Sebagai wali kelas, saya bisa memantau kehadiran siswa real-time dari HP. Kalau ada yang tidak masuk tanpa keterangan, saya langsung bisa follow up hari itu juga.',
        name: 'Budi Prasetyo',
        title: 'Guru & Wali Kelas',
        school: 'SMA Negeri 2 Semarang',
    },
];

function TestimonialCard({ testimonial }: { testimonial: Testimonial }) {
    return (
        <div className="bg-card rounded-2xl border border-border p-6 transition-all duration-200 hover:scale-[1.02] hover:border-[var(--primary)]">
            <div className="bg-accent text-accent-foreground mb-4 flex size-10 items-center justify-center rounded-full text-xs font-bold">
                {testimonial.initials}
            </div>
            <p className="text-foreground text-sm leading-relaxed italic">
                &ldquo;{testimonial.quote}&rdquo;
            </p>
            <div className="mt-4">
                <p className="text-foreground text-sm font-bold">{testimonial.name}</p>
                <p className="text-muted-foreground text-xs">
                    {testimonial.title} &middot; {testimonial.school}
                </p>
            </div>
        </div>
    );
}

export function TestimonialSection() {
    return (
        <section id="testimonials" className="relative overflow-hidden bg-gradient-to-b from-rose-50/60 to-pink-50/40 py-24 sm:py-32 lg:py-40 dark:from-rose-950/20 dark:to-pink-950/15">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mx-auto mb-14 max-w-2xl text-center animate-fade-up">
                    <h2 className="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">
                        Apa Kata Mereka
                    </h2>
                    <p className="text-muted-foreground mt-4 text-lg">
                        Sekolah-sekolah di seluruh Indonesia sudah merasakan manfaatnya.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    {testimonials.map((testimonial) => (
                        <TestimonialCard key={testimonial.name} testimonial={testimonial} />
                    ))}
                </div>
            </div>
        </section>
    );
}
