import { Form, Head, router, usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useState } from 'react';
import { DaftarStaf } from '@/components/general/daftar-staf';
import { FilterPenyelenggara } from '@/components/general/filter-penyelenggara';
import type { ModulGeneral, Staf } from '@/components/general/form-staf';
import { Halaman } from '@/components/general/halaman';
import { UndangAdmin } from '@/components/general/undang-admin';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { dashboard, pengguna as penggunaRoute } from '@/routes/general';
import { sandi, status } from '@/routes/general/pengguna';

type Akun = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    role: string | null;
    penyelenggara: { id: string; name: string | null };
    last_login_at: string | null;
};

type Props = {
    tab: 'staf' | 'penyelenggara';
    staf: Staf[];
    katalogModul: ModulGeneral[];
    bolehBeriPengguna: boolean;
    pengguna: {
        data: Akun[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    penyelenggaras: { id: string; name: string }[];
    filters: {
        penyelenggara: string;
        peran: string;
        status: string;
        q: string;
    };
};

const PERAN: Record<string, string> = {
    ADMIN_PENYELENGGARA: 'Admin',
    PENDAMPING: 'Pendamping',
};

const TAB = [
    { nilai: 'staf', label: 'Staf General' },
    { nilai: 'penyelenggara', label: 'Akun Penyelenggara' },
] as const;

/** Staf General (CRUD + modul) dan akun admin & pendamping semua penyelenggara. */
export default function GeneralPengguna({
    tab,
    staf,
    katalogModul,
    bolehBeriPengguna,
    pengguna,
    penyelenggaras,
    filters,
}: Props) {
    const { errors, auth } = usePage<{
        errors: Record<string, string>;
        auth: { user: { id: string } | null };
    }>().props;
    const [resetUntuk, setResetUntuk] = useState<Akun | null>(null);

    const muat = (patch: Partial<Props['filters']>) =>
        router.get(
            penggunaRoute.url(),
            { ...filters, ...patch, tab: 'penyelenggara' },
            { preserveState: true, replace: true },
        );

    const pindahTab = (nilai: Props['tab']) =>
        router.get(
            penggunaRoute.url(),
            nilai === 'penyelenggara' ? { tab: nilai } : {},
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Pengguna" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Pengguna
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Staf Tyas di workspace ini dan akun semua
                            penyelenggara. Akun sekolah dan orang tua dikelola
                            di area sekolah.
                        </p>
                    </div>
                    {tab === 'penyelenggara' && (
                        <UndangAdmin penyelenggaras={penyelenggaras} />
                    )}
                </div>

                <div className="flex flex-wrap gap-2" role="tablist">
                    {TAB.map((t) => (
                        <button
                            key={t.nilai}
                            type="button"
                            role="tab"
                            aria-selected={tab === t.nilai}
                            onClick={() => pindahTab(t.nilai)}
                            className={cn(
                                'rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors',
                                tab === t.nilai
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'hover:bg-muted',
                            )}
                        >
                            {t.label}
                            {t.nilai === 'staf' && (
                                <span className="ml-1.5 tabular-nums opacity-70">
                                    {staf.length}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                {tab === 'staf' ? (
                    <DaftarStaf
                        staf={staf}
                        katalog={katalogModul}
                        bolehBeriPengguna={bolehBeriPengguna}
                        idSaya={auth?.user?.id ?? null}
                    />
                ) : (
                    <>
                        <div className="flex flex-col gap-2 lg:flex-row">
                            <FilterPenyelenggara
                                penyelenggaras={penyelenggaras}
                                nilai={filters.penyelenggara}
                                cari={filters.q}
                                placeholder="Cari nama atau email…"
                                onUbah={muat}
                            />
                            <select
                                aria-label="Peran"
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                value={filters.peran}
                                onChange={(e) =>
                                    muat({ peran: e.target.value })
                                }
                            >
                                <option value="">Semua peran</option>
                                <option value="ADMIN_PENYELENGGARA">
                                    Admin
                                </option>
                                <option value="PENDAMPING">Pendamping</option>
                            </select>
                            <select
                                aria-label="Status"
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                value={filters.status}
                                onChange={(e) =>
                                    muat({ status: e.target.value })
                                }
                            >
                                <option value="">Semua status</option>
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>

                        <InputError message={errors?.is_active} />

                        {pengguna.data.length === 0 ? (
                            <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                                Belum ada akun yang cocok.
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="p-3 font-medium">
                                                Nama
                                            </th>
                                            <th className="p-3 font-medium">
                                                Penyelenggara
                                            </th>
                                            <th className="p-3 font-medium">
                                                Peran
                                            </th>
                                            <th className="p-3 font-medium">
                                                Login terakhir
                                            </th>
                                            <th className="p-3 text-right font-medium">
                                                Aksi
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {pengguna.data.map((u) => (
                                            <tr key={u.id} className="border-t">
                                                <td className="p-3">
                                                    <span className="font-medium">
                                                        {u.name}
                                                    </span>
                                                    {!u.is_active && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="ml-2"
                                                        >
                                                            Nonaktif
                                                        </Badge>
                                                    )}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {u.email}
                                                    </span>
                                                </td>
                                                <td className="p-3 text-muted-foreground">
                                                    {u.penyelenggara.name ??
                                                        '—'}
                                                </td>
                                                <td className="p-3">
                                                    {PERAN[u.role ?? ''] ??
                                                        u.role}
                                                </td>
                                                <td className="p-3 text-muted-foreground tabular-nums">
                                                    {u.last_login_at
                                                        ? new Intl.DateTimeFormat(
                                                              'id-ID',
                                                              {
                                                                  dateStyle:
                                                                      'medium',
                                                                  timeStyle:
                                                                      'short',
                                                              },
                                                          ).format(
                                                              new Date(
                                                                  u.last_login_at,
                                                              ),
                                                          )
                                                        : 'Belum pernah'}
                                                </td>
                                                <td className="p-3">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setResetUntuk(u)
                                                            }
                                                        >
                                                            <KeyRound className="size-4" />{' '}
                                                            Reset sandi
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                window.confirm(
                                                                    u.is_active
                                                                        ? `Nonaktifkan akun ${u.name}? Ia tidak bisa login sampai diaktifkan lagi.`
                                                                        : `Aktifkan lagi akun ${u.name}?`,
                                                                ) &&
                                                                router.put(
                                                                    status.url(
                                                                        u.id,
                                                                    ),
                                                                    {
                                                                        is_active:
                                                                            !u.is_active,
                                                                    },
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            {u.is_active
                                                                ? 'Nonaktifkan'
                                                                : 'Aktifkan'}
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <Halaman
                            prev={pengguna.prev_page_url}
                            next={pengguna.next_page_url}
                            total={pengguna.total}
                            satuan="akun"
                        />
                    </>
                )}
            </div>

            <Dialog
                open={resetUntuk !== null}
                onOpenChange={(buka) => !buka && setResetUntuk(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reset sandi</DialogTitle>
                        <DialogDescription>
                            {resetUntuk?.name} wajib mengganti sandi ini saat
                            login berikutnya.
                        </DialogDescription>
                    </DialogHeader>
                    {resetUntuk && (
                        <Form
                            {...sandi.form(resetUntuk.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setResetUntuk(null)}
                            className="grid gap-3"
                        >
                            {({ processing, errors: e }) => (
                                <>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="password">
                                            Sandi baru
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            autoComplete="new-password"
                                            required
                                        />
                                        <InputError message={e.password} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="password_confirmation">
                                            Ulangi sandi baru
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            autoComplete="new-password"
                                            required
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setResetUntuk(null)}
                                        >
                                            Batal
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Simpan sandi
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

GeneralPengguna.layout = {
    breadcrumbs: [
        { title: 'Absensi General', href: dashboard() },
        { title: 'Pengguna', href: penggunaRoute() },
    ],
};
