import { Form, Head } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index, invite, update } from '@/routes/admin/penyelenggara';

type Penyelenggara = {
    id: string;
    name: string;
    slug: string;
    is_active: boolean;
    participant_quota: number | null;
    wa_daily_quota: number;
};

type Props = {
    penyelenggara: Penyelenggara;
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
};

export const ROLE_LABEL: Record<string, string> = {
    ADMIN_PENYELENGGARA: 'Admin Penyelenggara',
    PENDAMPING: 'Pendamping',
};

export const STATUS_UNDANGAN: Record<string, string> = {
    menunggu: 'Menunggu',
    dipakai: 'Dipakai',
    kedaluwarsa: 'Kedaluwarsa',
    dibatalkan: 'Dibatalkan',
};

export default function PenyelenggaraShow({
    penyelenggara,
    users,
    invitations,
}: Props) {
    return (
        <>
            <Head title={penyelenggara.name} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-bold tracking-tight">
                        {penyelenggara.name}
                    </h1>
                    <Badge
                        variant={
                            penyelenggara.is_active ? 'default' : 'secondary'
                        }
                    >
                        {penyelenggara.is_active ? 'Aktif' : 'Nonaktif'}
                    </Badge>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Data penyelenggara
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...update.form(penyelenggara.id)}
                                className="grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Nama</Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                defaultValue={
                                                    penyelenggara.name
                                                }
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor="participant_quota">
                                                    Kuota peserta
                                                </Label>
                                                <Input
                                                    id="participant_quota"
                                                    name="participant_quota"
                                                    type="number"
                                                    min={1}
                                                    defaultValue={
                                                        penyelenggara.participant_quota ??
                                                        ''
                                                    }
                                                    placeholder="Tanpa batas"
                                                />
                                                <InputError
                                                    message={
                                                        errors.participant_quota
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="wa_daily_quota">
                                                    Kuota WA / hari
                                                </Label>
                                                <Input
                                                    id="wa_daily_quota"
                                                    name="wa_daily_quota"
                                                    type="number"
                                                    min={0}
                                                    defaultValue={
                                                        penyelenggara.wa_daily_quota
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.wa_daily_quota
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="hidden"
                                                name="is_active"
                                                value="0"
                                            />
                                            <Checkbox
                                                name="is_active"
                                                value="1"
                                                defaultChecked={
                                                    penyelenggara.is_active
                                                }
                                            />
                                            Aktif — bila dimatikan, semua akun
                                            penyelenggara ini tidak bisa masuk.
                                        </label>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="justify-self-start"
                                        >
                                            Simpan perubahan
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Mail className="size-4" /> Undang admin
                                penyelenggara
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <Form
                                {...invite.form(penyelenggara.id)}
                                resetOnSuccess
                                className="flex flex-col gap-2 sm:flex-row sm:items-start"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid flex-1 gap-1">
                                            <Input
                                                name="email"
                                                type="email"
                                                placeholder="pemilik@biro.co.id"
                                                aria-label="Email"
                                                required
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Kirim undangan
                                        </Button>
                                    </>
                                )}
                            </Form>
                            <p className="text-xs text-muted-foreground">
                                Tautan undangan berlaku 7 hari dan hanya bisa
                                dipakai sekali.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Akun ({users.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {users.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada akun. Undang admin penyelenggara
                                pertama.
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
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
                                        <span className="flex gap-2">
                                            <Badge variant="outline">
                                                {ROLE_LABEL[u.role ?? ''] ??
                                                    u.role}
                                            </Badge>
                                            {!u.is_active && (
                                                <Badge variant="secondary">
                                                    Nonaktif
                                                </Badge>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Undangan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {invitations.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada undangan.
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {invitations.map((i) => (
                                    <li
                                        key={i.id}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                    >
                                        <span>{i.email}</span>
                                        <span className="flex gap-2">
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
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PenyelenggaraShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Penyelenggara', href: index() },
    ],
};
