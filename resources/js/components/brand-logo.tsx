import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

export default function BrandLogo({ className }: { className?: string }) {
    const { name, app } = usePage<{
        app: { logo: string | null };
    }>().props;

    return app?.logo ? (
        <img
            src={app.logo}
            alt={name}
            className={cn('shrink-0 object-contain', className)}
        />
    ) : (
        <AppLogoIcon className={className} />
    );
}
