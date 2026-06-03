import AppLogoIcon from '@/components/app-logo-icon';
import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { currentSchool } = usePage<{ currentSchool?: { id: string; name: string; logo?: string | null } | null }>().props;
    const schoolName = currentSchool?.name ?? 'Tyas Photo';
    const logoUrl = currentSchool?.logo;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground overflow-hidden">
                {logoUrl ? (
                    <img src={logoUrl} alt={schoolName} className="size-full object-contain" />
                ) : (
                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {schoolName}
                </span>
            </div>
        </>
    );
}
