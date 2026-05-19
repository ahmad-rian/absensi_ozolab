import { Check } from 'lucide-react';

interface TenantFeature {
    title: string;
    description: string;
}

const features: TenantFeature[] = [
    {
        title: 'Isolasi Data Total',
        description: 'Setiap sekolah memiliki database terisolasi, menjamin privasi dan keamanan data.',
    },
    {
        title: 'Sub-domain Custom',
        description: 'Akses sistem melalui sub-domain khusus sekolah, seperti smpn1.absenku.id.',
    },
    {
        title: 'Branding Independen',
        description: 'Logo, warna, dan identitas visual masing-masing sekolah tampil secara mandiri.',
    },
    {
        title: 'Multi-Tim per Sekolah',
        description: 'Admin, guru, dan staf TU dapat dikelola dalam tim terpisah dengan peran berbeda.',
    },
    {
        title: 'Pengaturan Independen',
        description: 'Jam masuk, aturan absensi, dan notifikasi diatur per sekolah tanpa saling memengaruhi.',
    },
];

const schoolBoxes = [
    { name: 'SMP Negeri 1 Jakarta', color: 'bg-chart-1' },
    { name: 'SD Islam Terpadu Cahaya', color: 'bg-chart-2' },
    { name: 'SMA Kristen Petra', color: 'bg-chart-4' },
];

export function MultiTenantSection() {
    return (
        <section id="multi-tenant" className="bg-violet-50/70 py-24 sm:py-32 lg:py-40 dark:bg-violet-950/20">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mx-auto mb-14 max-w-2xl text-center animate-fade-up">
                    <h2 className="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">
                        Dibangun untuk Skala, Dirancang untuk Privasi
                    </h2>
                </div>

                <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                    {/* Left: Visual */}
                    <div className="animate-fade-up flex justify-center">
                        <div className="relative w-full max-w-sm">
                            {schoolBoxes.map((school, index) => (
                                <div
                                    key={school.name}
                                    className="bg-card relative rounded-xl border border-border p-4 transition-all duration-200 hover:scale-[1.02]"
                                    style={{
                                        marginTop: index > 0 ? '-1rem' : undefined,
                                        marginLeft: `${index * 1.5}rem`,
                                        zIndex: index,
                                    }}
                                >
                                    <div className="flex items-center gap-3">
                                        <div className={`size-3 shrink-0 rounded-full ${school.color}`} />
                                        <span className="text-foreground text-sm font-medium">
                                            {school.name}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Right: Feature list */}
                    <div className="animate-fade-up animation-delay-200 space-y-5">
                        {features.map((feature) => (
                            <div key={feature.title} className="flex gap-3">
                                <div className="bg-accent mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md">
                                    <Check className="size-4 text-[var(--primary)]" />
                                </div>
                                <div>
                                    <h3 className="text-foreground text-sm font-bold">{feature.title}</h3>
                                    <p className="text-muted-foreground mt-0.5 text-sm leading-relaxed">
                                        {feature.description}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
