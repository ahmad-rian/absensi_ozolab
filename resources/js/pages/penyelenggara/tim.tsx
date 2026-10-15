import { Form, Head, router } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ROLE_LABEL, STATUS_UNDANGAN } from '@/pages/general/penyelenggara/show';
import { dashboard } from '@/routes/penyelenggara';
import {
    index,
    invite,
    peran,
    revoke,
    status,
} from '@/routes/penyelenggara/tim';

type Props = {
    users: {
        id: string;
        name: string;
        email: string;
        is_active: boolean;
        role: string | null;
    }[];
    invitations: {
        id: string;
        email: string;
        role: string;
        status: string;
        expires_at: string;
    }[];
    errors: Record<string, string>;
};

export default function Tim({ users, invitations, errors }: Props) {
    return (
        <>
            <Head title="Tim" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Tim</h1>
                    <p className="text-sm text-muted-foreground">
                        Admin mengelola semua data; pendamping mencatat
                        kehadiran rombongan yang ditugaskan.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Undang anggota
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...invite.form()}
                            resetOnSuccess
                            className="flex flex-col gap-2 sm:flex-row sm:items-start"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid flex-1 gap-1">
                                        <Input
                                            name="email"
                                            type="email"
                                            placeholder="ustadz@biro.co.id"
                                            aria-label="Email"
                                            required
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                    <select
                                        name="role"
                                        aria-label="Peran"
                                        defaultValue="PENDAMPING"
                                        className="h-9 rounded-md border bg-background px-2 text-sm"
                                    >
                                        <option value="PENDAMPING">
                                            Pendamping
                                        </option>
                                        <option value="ADMIN_PENYELENGGARA">
                                            Admin Penyelenggara
                                        </option>
                                    </select>
                                    <Button type="submit" disabled={processing}>
                                        Kirim undangan
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Anggota ({users.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y text-sm">
                            {(errors.role || errors.is_active) && (
                                <li className="py-2">
                                    <InputError
                                        message={
                                            errors.role ?? errors.is_active
                                        }
                                    />
                                </li>
                            )}
                            {users.map((u) => (
                                <li
                                    key={u.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {u.name}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            {u.email}
                                        </span>
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <select
                                            aria-label={`Peran ${u.name}`}
                                            className="h-8 rounded-md border bg-background px-2 text-sm"
                                            value={u.role ?? ''}
                                            onChange={(e) =>
                                                window.confirm(
                                                    `Ubah peran ${u.name} menjadi ${ROLE_LABEL[e.target.value]}?`,
                                                ) &&
                                                router.put(
                                                    peran.url(u.id),
                                                    { role: e.target.value },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {[
                                                'ADMIN_PENYELENGGARA',
                                                'PENDAMPING',
                                            ].map((r) => (
                                                <option key={r} value={r}>
                                                    {ROLE_LABEL[r]}
                                                </option>
                                            ))}
                                        </select>
                                        <Button
                                            size="sm"
                                            variant={
                                                u.is_active
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                            onClick={() =>
                                                router.put(
                                                    status.url(u.id),
                                                    { is_active: !u.is_active },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {u.is_active
                                                ? 'Nonaktifkan'
                                                : 'Aktifkan'}
                                        </Button>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                {invitations.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Undangan
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y text-sm">
                                {invitations.map((i) => (
                                    <li
                                        key={i.id}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                    >
                                        <span>{i.email}</span>
                                        <span className="flex items-center gap-2">
                                            <Badge variant="outline">
                                                {ROLE_LABEL[i.role] ?? i.role}
                                            </Badge>
                                            <Badge
                                                variant={
                                                    i.status === 'menunggu'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {STATUS_UNDANGAN[i.status] ??
                                                    i.status}
                                            </Badge>
                                            {i.status === 'menunggu' && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        window.confirm(
                                                            `Batalkan undangan untuk ${i.email}?`,
                                                        ) &&
                                                        router.delete(
                                                            revoke.url(i.id),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Batalkan
                                                </Button>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Tim.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Tim', href: index() },
    ],
};
