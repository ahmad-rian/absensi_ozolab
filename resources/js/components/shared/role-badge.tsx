import { Badge } from '@/components/ui/badge';

const roleConfig: Record<string, { label: string; variant: 'default' | 'secondary' | 'outline' }> = {
    ADMIN: { label: 'Admin', variant: 'default' },
    GURU: { label: 'Guru', variant: 'secondary' },
    ORANG_TUA: { label: 'Orang Tua', variant: 'outline' },
};

export function RoleBadge({ role }: { role: string }) {
    const config = roleConfig[role] ?? { label: role, variant: 'outline' as const };

    return <Badge variant={config.variant}>{config.label}</Badge>;
}
