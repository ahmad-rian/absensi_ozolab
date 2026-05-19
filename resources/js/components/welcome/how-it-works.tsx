import { CreditCard, QrCode, School } from 'lucide-react';
import type { ReactNode } from 'react';

interface Step {
    number: string;
    icon: ReactNode;
    title: string;
    description: string;
}

const steps: Step[] = [
    {
        number: '01',
        icon: <School className="size-6 text-[var(--primary)]" />,
        title: 'Daftarkan Sekolah',
        description: 'Buat akun dan masukkan data sekolah. Proses pendaftaran hanya butuh beberapa menit.',
    },
    {
        number: '02',
        icon: <CreditCard className="size-6 text-[var(--primary)]" />,
        title: 'Cetak Kartu QR',
        description: 'Setiap siswa mendapat kartu QR unik yang dapat dicetak langsung dari sistem.',
    },
    {
        number: '03',
        icon: <QrCode className="size-6 text-[var(--primary)]" />,
        title: 'Scan & Selesai',
        description: 'Siswa cukup scan kartu saat datang. Kehadiran tercatat dan orang tua langsung diberitahu.',
    },
];

function ConnectorLine() {
    return (
        <svg
            className="text-border hidden h-0.5 flex-1 md:block"
            viewBox="0 0 200 2"
            fill="none"
            preserveAspectRatio="none"
        >
            <line
                x1="0"
                y1="1"
                x2="200"
                y2="1"
                stroke="currentColor"
                strokeWidth="2"
                strokeDasharray="6 4"
            />
        </svg>
    );
}

function StepCard({ step, index }: { step: Step; index: number }) {
    const delayClass = index === 1 ? 'animation-delay-200' : index === 2 ? 'animation-delay-400' : '';
    return (
        <div className={`relative flex flex-col items-center text-center animate-fade-up ${delayClass}`}>
            <div className="relative mb-5">
                <span className="text-muted-foreground/10 pointer-events-none absolute -top-4 left-1/2 -translate-x-1/2 text-7xl font-black select-none">
                    {step.number}
                </span>
                <div className="bg-accent relative z-10 flex size-14 items-center justify-center rounded-xl shadow-sm">
                    {step.icon}
                </div>
            </div>
            <h3 className="text-foreground text-lg font-bold">{step.title}</h3>
            <p className="text-muted-foreground mt-2 max-w-xs text-sm leading-relaxed">{step.description}</p>
        </div>
    );
}

export function HowItWorks() {
    return (
        <section id="how-it-works" className="relative overflow-hidden bg-gradient-to-b from-emerald-50/70 to-teal-50/50 py-24 sm:py-32 lg:py-40 dark:from-emerald-950/20 dark:to-teal-950/15">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mx-auto mb-14 max-w-2xl text-center animate-fade-up">
                    <h2 className="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">Cara Kerja</h2>
                    <p className="text-muted-foreground mt-4 text-lg">
                        Tiga langkah sederhana untuk memulai sistem absensi digital di sekolah Anda.
                    </p>
                </div>

                <div className="flex flex-col items-center gap-12 md:flex-row md:items-start md:justify-between md:gap-0">
                    <StepCard step={steps[0]} index={0} />
                    <ConnectorLine />
                    <StepCard step={steps[1]} index={1} />
                    <ConnectorLine />
                    <StepCard step={steps[2]} index={2} />
                </div>
            </div>
        </section>
    );
}
