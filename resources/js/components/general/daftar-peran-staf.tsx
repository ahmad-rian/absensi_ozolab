import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { PeranStaf } from '@/components/general/form-staf';
import { MatriksModul } from '@/components/general/matriks-modul';
import type { GrupModul } from '@/components/general/matriks-modul';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store, update } from '@/routes/general/peran-staf';

/** Peran staf = template modul yang bisa dibuat sendiri dan dipasang ke staf. */
export function DaftarPeranStaf({
    peran,
    katalog,
    bolehKhusus,
    error,
}: {
    peran: PeranStaf[];
    katalog: GrupModul[];
    bolehKhusus: boolean;
    error?: string;
}) {
    const [ubah, setUbah] = useState<PeranStaf | 'baru' | null>(null);

    const hapus = (p: PeranStaf) => {
        if (window.confirm(`Hapus peran "${p.name}"?`)) {
            router.delete(destroy.url(p.id), { preserveScroll: true });
        }
    };

    return (
        <div className="grid gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    Buat peran sesuai pembagian kerja, mis. "Operator Haji" atau
                    "Admin Kartu". Mengubah peran langsung berlaku untuk semua
                    staf yang memakainya.
                </p>
                <Button onClick={() => setUbah('baru')}>
                    <Plus className="size-4" /> Tambah peran
                </Button>
            </div>
            <InputError message={error} />

            {peran.length === 0 ? (
                <p className="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground">
                    Belum ada peran staf. Tanpa peran pun, modul tetap bisa
                    dicentang langsung per akun.
                </p>
            ) : (
                <div className="grid gap-3 md:grid-cols-2">
                    {peran.map((p) => (
                        <div
                            key={p.id}
                            className="grid gap-2 rounded-lg border p-4"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <p className="font-semibold">{p.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {p.modul.length} modul · dipakai{' '}
                                        {p.jumlah_staf} staf
                                    </p>
                                </div>
                                <div className="flex gap-1.5">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setUbah(p)}
                                    >
                                        <Pencil className="size-4" /> Ubah
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        aria-label={`Hapus peran ${p.name}`}
                                        onClick={() => hapus(p)}
                                    >
                                        <Trash2 className="size-4 text-destructive" />
                                    </Button>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-1">
                                {katalog
                                    .flatMap((g) => g.modul)
                                    .filter((m) =>
                                        p.modul.includes(m.permission),
                                    )
                                    .map((m) => (
                                        <Badge
                                            key={m.permission}
                                            variant="outline"
                                        >
                                            {m.label}
                                        </Badge>
                                    ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {ubah !== null && (
                <FormPeran
                    key={ubah === 'baru' ? 'baru' : ubah.id}
                    peran={ubah === 'baru' ? null : ubah}
                    katalog={katalog}
                    bolehKhusus={bolehKhusus}
                    onTutup={() => setUbah(null)}
                />
            )}
        </div>
    );
}

function FormPeran({
    peran,
    katalog,
    bolehKhusus,
    onTutup,
}: {
    peran: PeranStaf | null;
    katalog: GrupModul[];
    bolehKhusus: boolean;
    onTutup: () => void;
}) {
    const form = useForm({
        name: peran?.name ?? '',
        modul: peran?.modul ?? [],
    });

    const simpan = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit(peran ? update(peran.id) : store(), {
            preserveScroll: true,
            onSuccess: onTutup,
        });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onTutup()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {peran
                            ? `Ubah peran ${peran.name}`
                            : 'Tambah peran staf'}
                    </DialogTitle>
                    <DialogDescription>
                        Centang modul yang dibuka peran ini.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={simpan} className="grid gap-5">
                    <div className="grid gap-1.5">
                        <Label htmlFor="peran-nama">Nama peran</Label>
                        <Input
                            id="peran-nama"
                            value={form.data.name}
                            maxLength={60}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="mis. Operator Haji"
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <MatriksModul
                        katalog={katalog}
                        nilai={form.data.modul}
                        onUbah={(nilai) => form.setData('modul', nilai)}
                        bolehKhusus={bolehKhusus}
                    />
                    <InputError message={form.errors.modul} />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onTutup}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {peran ? 'Simpan peran' : 'Buat peran'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
