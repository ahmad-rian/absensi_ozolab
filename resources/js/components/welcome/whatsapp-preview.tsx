import { CheckCheck, MessageCircle, Shield, Smartphone } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const MESSAGE_TEXT =
    'Halo Bapak/Ibu, ananda Budi Santoso (Kelas 7A) telah HADIR di SMP Nusantara pada 19 Mei 2026 pukul 07:02. Terima kasih.';

const highlights = [
    {
        icon: MessageCircle,
        title: 'Notifikasi Instan',
        description: 'Pesan WhatsApp terkirim otomatis dalam hitungan detik setelah siswa melakukan scan QR.',
    },
    {
        icon: Shield,
        title: 'Privasi Terjaga',
        description: 'Nomor orang tua hanya digunakan untuk notifikasi kehadiran, tidak untuk keperluan lain.',
    },
    {
        icon: Smartphone,
        title: 'Tanpa Instal Aplikasi',
        description: 'Orang tua cukup punya WhatsApp. Tidak perlu mengunduh atau mendaftar aplikasi tambahan.',
    },
];

function TypingDots() {
    return (
        <div className="flex items-center gap-0.5 px-1">
            {[0, 1, 2].map((i) => (
                <span
                    key={i}
                    className="inline-block size-1.5 rounded-full bg-zinc-400 animate-typing-dot"
                    style={{ animationDelay: `${i * 0.15}s` }}
                />
            ))}
        </div>
    );
}

function PhoneMockup() {
    const ref = useRef<HTMLDivElement>(null);
    const [isInView, setIsInView] = useState(false);
    const [showMessage, setShowMessage] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setIsInView(true);
                    observer.disconnect();
                }
            },
            { rootMargin: '-80px' },
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (isInView) {
            const timer = setTimeout(() => setShowMessage(true), 1500);
            return () => clearTimeout(timer);
        }
    }, [isInView]);

    return (
        <div ref={ref} className="flex justify-center lg:justify-start">
            <div className="w-[280px] overflow-hidden rounded-[2rem] border-4 border-zinc-800 bg-zinc-900 shadow-2xl dark:border-zinc-700">
                {/* Status bar */}
                <div className="flex items-center justify-between bg-zinc-900 px-5 py-1.5 text-[10px] text-zinc-400">
                    <span>07:02</span>
                    <div className="flex items-center gap-1">
                        <svg className="size-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <rect x="1" y="14" width="4" height="8" rx="1" />
                            <rect x="7" y="10" width="4" height="12" rx="1" />
                            <rect x="13" y="6" width="4" height="16" rx="1" />
                            <rect x="19" y="2" width="4" height="20" rx="1" />
                        </svg>
                        <span>100%</span>
                    </div>
                </div>

                {/* WhatsApp header */}
                <div className="flex items-center gap-2.5 bg-green-700 px-3 py-2.5">
                    <div className="flex size-8 items-center justify-center rounded-full bg-green-600">
                        <span className="text-xs font-bold text-white">SA</span>
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-white">SMP Nusantara</p>
                        <p className="text-[10px] text-green-200">online</p>
                    </div>
                </div>

                {/* Chat area */}
                <div className="flex min-h-[220px] flex-col justify-end bg-[#ece5dd] p-3 dark:bg-[#0b141a]">
                    {/* Typing indicator */}
                    {isInView && !showMessage && (
                        <div className="mb-1 w-fit rounded-lg rounded-tl-none bg-white px-3 py-2 shadow-sm animate-fade-up dark:bg-zinc-800">
                            <TypingDots />
                        </div>
                    )}

                    {/* Message bubble */}
                    {showMessage && (
                        <div className="w-full max-w-[230px] rounded-lg rounded-tl-none bg-white px-3 py-2 shadow-sm animate-message-reveal dark:bg-zinc-800">
                            <p className="text-[11px] leading-relaxed text-zinc-800 dark:text-zinc-200">
                                {MESSAGE_TEXT}
                            </p>
                            <div className="mt-1 flex items-center justify-end gap-1">
                                <span className="text-[9px] text-zinc-400">07:02</span>
                                <CheckCheck className="size-3.5 text-blue-500" />
                            </div>
                        </div>
                    )}
                </div>

                {/* Input bar */}
                <div className="flex items-center gap-2 bg-[#f0f0f0] px-3 py-2 dark:bg-zinc-800">
                    <div className="flex-1 rounded-full bg-white px-3 py-1.5 text-[10px] text-zinc-400 dark:bg-zinc-700 dark:text-zinc-500">
                        Ketik pesan
                    </div>
                    <div className="flex size-7 items-center justify-center rounded-full bg-green-600">
                        <svg className="size-3.5 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    );
}

export function WhatsappPreview() {
    return (
        <section id="whatsapp" className="bg-green-50/70 py-24 sm:py-32 lg:py-40 dark:bg-green-950/20">
            <div className="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
                <div className="order-2 lg:order-1 animate-fade-up">
                    <PhoneMockup />
                </div>

                <div className="order-1 lg:order-2 animate-fade-up animation-delay-200">
                    <h2 className="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">
                        Orang Tua Tenang, Anak Aman
                    </h2>
                    <p className="text-muted-foreground mt-4 text-lg leading-relaxed">
                        Setiap kali siswa melakukan scan QR Code di sekolah, orang tua langsung menerima
                        notifikasi WhatsApp. Transparansi penuh tanpa ribet.
                    </p>
                    <ul className="mt-8 space-y-6">
                        {highlights.map((item) => (
                            <li key={item.title} className="flex gap-4">
                                <span className="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-green-100 dark:bg-green-950">
                                    <item.icon className="size-5 text-green-600 dark:text-green-400" />
                                </span>
                                <div>
                                    <h3 className="text-foreground text-sm font-semibold">{item.title}</h3>
                                    <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                        {item.description}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </section>
    );
}
