export function BgBlobs() {
    return (
        <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div
                className="absolute -top-24 -right-24 size-[500px] rounded-full opacity-20 blur-[120px] dark:opacity-30"
                style={{ background: 'var(--chart-1)' }}
            />
            <div
                className="absolute -bottom-32 -left-32 size-[400px] rounded-full opacity-15 blur-[100px] dark:opacity-25"
                style={{ background: 'var(--chart-2)' }}
            />
        </div>
    );
}
