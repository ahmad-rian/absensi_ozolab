import { Check, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface PricingFeature {
    text: string;
    included: boolean;
}

interface PricingTier {
    name: string;
    price: string;
    priceNote?: string;
    features: PricingFeature[];
    cta: string;
    highlighted?: boolean;
}

const tiers: PricingTier[] = [
    {
        name: 'Starter',
        price: 'Gratis',
        priceNote: 'Selamanya',
        features: [
            { text: 'Hingga 100 siswa', included: true },
            { text: '1 admin', included: true },
            { text: 'Notifikasi WA basic', included: true },
            { text: 'Branding AbsenKu', included: true },
            { text: 'Custom branding', included: false },
            { text: 'Priority support', included: false },
        ],
        cta: 'Mulai Gratis',
    },
    {
        name: 'Pro',
        price: 'Rp 2.500',
        priceNote: '/siswa/bulan',
        features: [
            { text: 'Unlimited siswa', included: true },
            { text: 'Multi-admin', included: true },
            { text: 'Custom branding', included: true },
            { text: 'Sub-domain sekolah', included: true },
            { text: 'Priority support', included: true },
            { text: 'Notifikasi WA tanpa batas', included: true },
        ],
        cta: 'Coba Pro 30 Hari',
        highlighted: true,
    },
    {
        name: 'Enterprise',
        price: 'Hubungi Sales',
        features: [
            { text: 'Multi-cabang', included: true },
            { text: 'White-label penuh', included: true },
            { text: 'SSO & integrasi', included: true },
            { text: 'Akses API', included: true },
            { text: 'Dedicated CSM', included: true },
            { text: 'SLA terjamin', included: true },
        ],
        cta: 'Jadwalkan Demo',
    },
];

function PricingCard({ tier }: { tier: PricingTier }) {
    const isHighlighted = tier.highlighted;

    return (
        <div
            className={`bg-card relative flex flex-col rounded-2xl border p-6 transition-all duration-200 hover:scale-[1.02] ${
                isHighlighted ? 'border-2 border-[var(--primary)]' : 'border-border'
            }`}
        >
            {isHighlighted && (
                <Badge className="absolute -top-3 left-1/2 -translate-x-1/2">
                    Paling Populer
                </Badge>
            )}

            <div className="mb-6">
                <h3 className="text-foreground text-lg font-bold">{tier.name}</h3>
                <div className="mt-3">
                    <span className="text-foreground text-3xl font-bold">{tier.price}</span>
                    {tier.priceNote && (
                        <span className="text-muted-foreground ml-1 text-sm">{tier.priceNote}</span>
                    )}
                </div>
            </div>

            <ul className="mb-8 flex-1 space-y-3">
                {tier.features.map((feature) => (
                    <li key={feature.text} className="flex items-start gap-2">
                        {feature.included ? (
                            <Check className="mt-0.5 size-4 shrink-0 text-[var(--primary)]" />
                        ) : (
                            <X className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                        )}
                        <span
                            className={`text-sm ${
                                feature.included ? 'text-foreground' : 'text-muted-foreground'
                            }`}
                        >
                            {feature.text}
                        </span>
                    </li>
                ))}
            </ul>

            <Button
                variant={isHighlighted ? 'default' : 'outline'}
                size="lg"
                className="w-full"
            >
                {tier.cta}
            </Button>
        </div>
    );
}

export function PricingSection() {
    return (
        <section id="pricing" className="py-20 sm:py-28">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mx-auto mb-14 max-w-2xl text-center animate-fade-up">
                    <h2 className="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">
                        Harga Transparan, untuk Sekolah Segala Ukuran
                    </h2>
                    <p className="text-muted-foreground mt-4 text-lg">
                        Mulai gratis, upgrade kapan saja. Tanpa biaya tersembunyi.
                    </p>
                </div>

                <div className="mx-auto grid max-w-5xl gap-6 md:grid-cols-3">
                    {tiers.map((tier) => (
                        <PricingCard key={tier.name} tier={tier} />
                    ))}
                </div>
            </div>
        </section>
    );
}
