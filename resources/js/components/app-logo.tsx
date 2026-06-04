import AppLogoIcon from '@/components/app-logo-icon';
import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { currentSchool, app } = usePage<{
        currentSchool?: { name: string } | null;
        app?: { logo?: string | null } | null;
    }>().props;
    const schoolName = currentSchool?.name ?? 'Tyas Photo';
    const logoUrl = (app as { logo?: string | null } | null)?.logo;

    return (
        <>
            {logoUrl ? (
                <img src={logoUrl} alt={schoolName} className="size-8 shrink-0 rounded-md object-contain" />
            ) : (
                <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                </div>
            )}
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {schoolName}
                </span>
            </div>
        </>
    );
}
