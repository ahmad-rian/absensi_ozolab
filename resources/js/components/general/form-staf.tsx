import { useForm } from '@inertiajs/react';
import { MatriksModul } from '@/components/general/matriks-modul';
import type { GrupModul } from '@/components/general/matriks-modul';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
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

export type PeranStaf = {
    id: string;
    name: string;
    modul: string[];
    jumlah_staf: number;
};

export type Staf = {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    peran: { id: string; name: string } | null;
    modul: string[];
    modul_efektif: string[];
    last_login_at: string | null;
};

type DataStaf = {
    name: string;
    email: string;
    phone: string;
    password: string;
    password_confirmation: string;
    peran_id: string;
    modul: string[];
};

/**
 * Tambah / ubah akun staf General: peran (template modul) + modul tambahan
 * per akun. Modul khusus hanya bisa diberikan super admin (server menjaga).
 */
export function FormStaf({
    staf,
    katalog,
    peranStaf,
    bolehBeriPengguna,
    onTutup,
}: {
    staf: Staf | null;
    katalog: GrupModul[];
    peranStaf: PeranStaf[];
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
        peran_id: staf?.peran?.id ?? '',
        modul: staf?.modul ?? [],
    });
    const modulPeran =
        peranStaf.find((p) => p.id === form.data.peran_id)?.modul ?? [];

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

                    <div className="grid gap-1.5">
                        <Label htmlFor="staf-peran">Peran staf</Label>
                        <select
                            id="staf-peran"
                            value={form.data.peran_id}
                            onChange={(e) =>
                                form.setData('peran_id', e.target.value)
                            }
                            className="h-9 rounded-md border bg-background px-2 text-sm"
                        >
                            <option value="">
                                Tanpa peran — centang modul sendiri
                            </option>
                            {peranStaf.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name} ({p.modul.length} modul)
                                </option>
                            ))}
                        </select>
                        <p className="text-xs text-muted-foreground">
                            Modul dari peran ikut berubah bila perannya diubah.
                            Centang di bawah untuk modul tambahan khusus akun
                            ini.
                        </p>
                        <InputError message={form.errors.peran_id} />
                    </div>

                    <fieldset className="grid gap-2">
                        <legend className="mb-2 text-sm font-medium">
                            Hak akses modul
                        </legend>
                        <MatriksModul
                            katalog={katalog}
                            nilai={form.data.modul}
                            onUbah={(nilai) => form.setData('modul', nilai)}
                            dariPeran={modulPeran}
                            bolehKhusus={bolehBeriPengguna}
                        />
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
