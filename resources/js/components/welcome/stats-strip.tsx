import { AnimatedCounter } from '@/components/welcome/animated-counter';

const stats = [
    { target: 200, suffix: '+', label: 'Sekolah', decimals: 0 },
    { target: 50000, suffix: '+', label: 'Siswa', decimals: 0 },
    { target: 1.2, suffix: ' Juta', label: 'Absensi/Bulan', decimals: 1 },
    { target: 99.9, suffix: '%', label: 'Uptime', decimals: 1 },
];

export function StatsStrip() {
    return (
        <section className="border-y border-border bg-muted/50">
            <div
                className="mx-auto grid max-w-7xl grid-cols-2 gap-y-8 px-4 py-12 sm:px-6 md:grid-cols-4 md:gap-y-0 lg:px-8"
            >
                {stats.map((stat, i) => (
                    <div
                        key={stat.label}
                        className={`flex flex-col items-center text-center animate-fade-up ${
                            i > 0 ? 'md:border-l md:border-border' : ''
                        } ${i === 1 ? 'animation-delay-200' : i === 2 ? 'animation-delay-400' : i === 3 ? 'animation-delay-500' : ''}`}
                    >
                        <span className="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">
                            <AnimatedCounter
                                target={stat.target}
                                suffix={stat.suffix}
                                decimals={stat.decimals}
                            />
                        </span>
                        <span className="text-muted-foreground mt-1 text-sm font-medium">
                            {stat.label}
                        </span>
                    </div>
                ))}
            </div>
        </section>
    );
}
