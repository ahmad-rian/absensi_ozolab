import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/general/staf';

export type ModulGeneral = {
    permission: string;
    label: string;
    keterangan: string | null;
};

export type Staf = {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    modul: string[];
    last_login_at: string | null;
};

const MODUL_PENGGUNA = 'general-pengguna.access';

type DataStaf = {
    name: string;
    email: string;
    phone: string;
    password: string;
    password_confirmation: string;
    modul: string[];
};

/**
 * Tambah / ubah akun staf General + matriks modul yang boleh dibuka.
 * Modul Pengguna hanya bisa diberikan super admin (server juga menjaga).
 */
export function FormStaf({
    staf,
    katalog,
    bolehBeriPengguna,
    onTutup,
}: {
    staf: Staf | null;
    katalog: ModulGeneral[];
    bolehBeriPengguna: boolean;
    onTutup: () => void;
}) {
    const baru = staf === null;
    const form = useForm<DataStaf>({
        name: staf?.name ?? '',
        email: staf?.email ?? '',
        phone: staf?.phone ?? '',
        password: '',
        password_confirmation: '',
        modul: staf?.modul ?? [],
    });

    const terkunci = (permission: string) =>
        permission === MODUL_PENGGUNA && !bolehBeriPengguna;
    const bisaDipilih = katalog.filter((m) => !terkunci(m.permission));
    const semuaTerpilih = bisaDipilih.every((m) =>
        form.data.modul.includes(m.permission),
    );

    const ubahModul = (permission: string, pilih: boolean) =>
        form.setData(
            'modul',
            pilih
                ? [...form.data.modul, permission]
                : form.data.modul.filter((p) => p !== permission),
        );

    const pilihSemua = () =>
        form.setData(
            'modul',
            semuaTerpilih
                ? form.data.modul.filter((p) => terkunci(p))
                : [
                      ...new Set([
                          ...form.data.modul,
                          ...bisaDipilih.map((m) => m.permission),
                      ]),
                  ],
        );

    const simpan = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit(baru ? store() : update(staf.id), {
            preserveScroll: true,
            onSuccess: onTutup,
        });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onTutup()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {baru ? 'Tambah staf General' : `Ubah ${staf.name}`}
                    </DialogTitle>
                    <DialogDescription>
                        Staf hanya melihat menu dari modul yang dicentang.
                        {baru &&
                            ' Sandi awal wajib diganti saat login pertama.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={simpan} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="staf-nama">Nama</Label>
                            <Input
                                id="staf-nama"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="staf-email">Email login</Label>
                            <Input
                                id="staf-email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.email} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="staf-telepon">
                                Telepon (opsional)
                            </Label>
                            <Input
                                id="staf-telepon"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                            />
                            <InputError message={form.errors.phone} />
                        </div>
                    </div>

                    {baru && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="staf-sandi">Sandi awal</Label>
                                <PasswordInput
                                    id="staf-sandi"
                                    value={form.data.password}
                                    onChange={(e) =>
                                        form.setData('password', e.target.value)
                                    }
                                    required
                                    autoComplete="new-password"
                                />
                                <InputError message={form.errors.password} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="staf-sandi-ulang">
                                    Ulangi sandi
                                </Label>
                                <PasswordInput
                                    id="staf-sandi-ulang"
                                    value={form.data.password_confirmation}
                                    onChange={(e) =>
                                        form.setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    autoComplete="new-password"
                                />
                            </div>
                        </div>
                    )}

                    <fieldset className="grid gap-2">
                        <div className="flex items-center justify-between">
                            <legend className="text-sm font-medium">
                                Hak akses modul
                            </legend>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={pilihSemua}
                            >
                                {semuaTerpilih ? 'Kosongkan' : 'Pilih semua'}
                            </Button>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {katalog.map((m) => {
                                const id = `modul-${m.permission}`;
                                const kunci = terkunci(m.permission);

                                return (
                                    <label
                                        key={m.permission}
                                        htmlFor={id}
                                        className="flex cursor-pointer items-start gap-3 rounded-lg border p-3 hover:bg-muted/40 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60"
                                    >
                                        <Checkbox
                                            id={id}
                                            checked={form.data.modul.includes(
                                                m.permission,
                                            )}
                                            disabled={kunci}
                                            onCheckedChange={(c) =>
                                                ubahModul(
                                                    m.permission,
                                                    c === true,
                                                )
                                            }
                                            className="mt-0.5"
                                        />
                                        <span className="grid gap-0.5">
                                            <span className="text-sm font-medium">
                                                {m.label}
                                            </span>
                                            {m.keterangan && (
                                                <span className="text-xs text-muted-foreground">
                                                    {m.keterangan}
                                                </span>
                                            )}
                                            {kunci && (
                                                <span className="text-xs text-amber-700 dark:text-amber-400">
                                                    Hanya super admin yang bisa
                                                    memberi modul ini.
                                                </span>
                                            )}
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                        <InputError message={form.errors.modul} />
                    </fieldset>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onTutup}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {baru ? 'Buat akun' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
