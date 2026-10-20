import { Form, router } from '@inertiajs/react';
import { KeyRound, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FormStaf } from '@/components/general/form-staf';
import type { ModulGeneral, Staf } from '@/components/general/form-staf';
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
import { destroy, sandi, status } from '@/routes/general/staf';

const waktu = (iso: string | null) =>
    iso
        ? new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(iso))
        : 'Belum pernah';

/** Akun staf Tyas di workspace General beserta modulnya. */
export function DaftarStaf({
    staf,
    katalog,
    bolehBeriPengguna,
    idSaya,
}: {
    staf: Staf[];
    katalog: ModulGeneral[];
    bolehBeriPengguna: boolean;
    idSaya: string | null;
}) {
    const [ubah, setUbah] = useState<Staf | 'baru' | null>(null);
    const [resetUntuk, setResetUntuk] = useState<Staf | null>(null);
    const label = (permission: string) =>
        katalog.find((m) => m.permission === permission)?.label ?? permission;

    const ubahStatus = (s: Staf) => {
        const pesan = s.is_active
            ? `Nonaktifkan ${s.name}? Ia tidak bisa membuka workspace General sampai diaktifkan lagi.`
            : `Aktifkan lagi ${s.name}?`;

        if (window.confirm(pesan)) {
            router.put(
                status.url(s.id),
                { is_active: !s.is_active },
                { preserveScroll: true },
            );
        }
    };

    const hapus = (s: Staf) => {
        if (
            window.confirm(
                `Hapus akun ${s.name}? Ia langsung kehilangan akses. Tindakan ini tidak bisa dibatalkan dari layar.`,
            )
        ) {
            router.delete(destroy.url(s.id), { preserveScroll: true });
        }
    };

    return (
        <div className="grid gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    Akun staf Tyas yang membantu mengelola workspace ini. Tiap
                    akun hanya membuka modul yang dicentang.
                </p>
                <Button onClick={() => setUbah('baru')}>
                    <Plus className="size-4" /> Tambah staf
                </Button>
            </div>

            {staf.length === 0 ? (
                <p className="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground">
                    Belum ada staf General. Tambah staf untuk berbagi pekerjaan
                    tanpa memberi akses super admin.
                </p>
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Nama</th>
                                <th className="p-3 font-medium">Modul</th>
                                <th className="p-3 font-medium">
                                    Login terakhir
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {staf.map((s) => {
                                const sendiri = s.id === idSaya;

                                return (
                                    <tr
                                        key={s.id}
                                        className="border-t align-top"
                                    >
                                        <td className="p-3">
                                            <span className="font-medium">
                                                {s.name}
                                            </span>
                                            {!s.is_active && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2"
                                                >
                                                    Nonaktif
                                                </Badge>
                                            )}
                                            <span className="block text-xs text-muted-foreground">
                                                {s.email}
                                            </span>
                                        </td>
                                        <td className="p-3">
                                            {s.modul.length === 0 ? (
                                                <span className="text-xs text-muted-foreground">
                                                    Belum ada modul
                                                </span>
                                            ) : (
                                                <div className="flex flex-wrap gap-1">
                                                    {s.modul.map((m) => (
                                                        <Badge
                                                            key={m}
                                                            variant="outline"
                                                        >
                                                            {label(m)}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {waktu(s.last_login_at)}
                                        </td>
                                        <td className="p-3">
                                            {sendiri ? (
                                                <span className="block text-right text-xs text-muted-foreground">
                                                    Akun Anda
                                                </span>
                                            ) : (
                                                <div className="flex flex-wrap justify-end gap-1.5">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setUbah(s)
                                                        }
                                                    >
                                                        <Pencil className="size-4" />{' '}
                                                        Ubah
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            ubahStatus(s)
                                                        }
                                                    >
                                                        {s.is_active
                                                            ? 'Nonaktifkan'
                                                            : 'Aktifkan'}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        aria-label={`Reset sandi ${s.name}`}
                                                        onClick={() =>
                                                            setResetUntuk(s)
                                                        }
                                                    >
                                                        <KeyRound className="size-4" />
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        aria-label={`Hapus ${s.name}`}
                                                        onClick={() => hapus(s)}
                                                    >
                                                        <Trash2 className="size-4 text-destructive" />
                                                    </Button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {ubah !== null && (
                <FormStaf
                    key={ubah === 'baru' ? 'baru' : ubah.id}
                    staf={ubah === 'baru' ? null : ubah}
                    katalog={katalog}
                    bolehBeriPengguna={bolehBeriPengguna}
                    onTutup={() => setUbah(null)}
                />
            )}

            <Dialog
                open={resetUntuk !== null}
                onOpenChange={(o) => !o && setResetUntuk(null)}
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
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="staf-sandi-baru">
                                            Sandi baru
                                        </Label>
                                        <PasswordInput
                                            id="staf-sandi-baru"
                                            name="password"
                                            required
                                            autoComplete="new-password"
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="staf-sandi-baru-ulang">
                                            Ulangi sandi
                                        </Label>
                                        <PasswordInput
                                            id="staf-sandi-baru-ulang"
                                            name="password_confirmation"
                                            required
                                            autoComplete="new-password"
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
        </div>
    );
}
