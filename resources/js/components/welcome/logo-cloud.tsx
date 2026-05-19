const schoolLogos = [
    'SMP N 1',
    'SD M 2',
    'SMA C 3',
    'MI N 4',
    'SMK T 5',
    'SD I 6',
];

function LogoCircle({ name }: { name: string }) {
    return (
        <div className="bg-muted text-muted-foreground mx-4 flex size-16 shrink-0 items-center justify-center rounded-full text-xs font-bold">
            {name}
        </div>
    );
}

function MarqueeRow({ direction }: { direction: 'left' | 'right' }) {
    const animationClass = direction === 'left' ? 'animate-marquee-left' : 'animate-marquee-right';

    return (
        <div className="relative flex overflow-hidden py-3">
            <div className={`flex shrink-0 gap-0 ${animationClass}`}>
                {schoolLogos.map((name) => (
                    <LogoCircle key={`a-${name}`} name={name} />
                ))}
            </div>
            <div className={`flex shrink-0 gap-0 ${animationClass}`} aria-hidden="true">
                {schoolLogos.map((name) => (
                    <LogoCircle key={`b-${name}`} name={name} />
                ))}
            </div>
        </div>
    );
}

export function LogoCloud() {
    return (
        <section className="bg-muted/30 py-16">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p className="text-muted-foreground mb-8 text-center text-sm font-medium">
                    Dipercaya oleh sekolah-sekolah terbaik di Indonesia
                </p>

                <div className="space-y-2">
                    <MarqueeRow direction="left" />
                    <MarqueeRow direction="right" />
                </div>
            </div>

            <style>{`
                @keyframes marquee-left {
                    0% { transform: translateX(0); }
                    100% { transform: translateX(-100%); }
                }
                @keyframes marquee-right {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(0); }
                }
                .animate-marquee-left {
                    animation: marquee-left 20s linear infinite;
                }
                .animate-marquee-right {
                    animation: marquee-right 20s linear infinite;
                }
            `}</style>
        </section>
    );
}
