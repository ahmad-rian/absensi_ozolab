import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { cn } from '@/lib/utils';
import { store } from '@/routes/general/kategori';

export type TemplateKategori = {
    key: string;
    name: string;
    istilah_peserta: string;
    istilah_grup: string;
};

/**
 * Tambah kategori untuk penyelenggara mana pun dari workspace general.
 * Setelah dibuat, server membuka editor kolomnya di area penyelenggara.
 */
export function TambahKategori({
    open,
    onOpenChange,
    penyelenggaras,
    templates,
    penyelenggaraAwal,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    penyelenggaras: { id: string; name: string }[];
    templates: TemplateKategori[];
    penyelenggaraAwal: string;
}) {
    const [template, setTemplate] = useState(templates[0]?.key ?? '');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Tambah kategori</DialogTitle>
                    <DialogDescription>
                        Pilih penyelenggara dan template. Kolom data peserta
                        bisa diubah setelahnya.
                    </DialogDescription>
                </DialogHeader>

                {penyelenggaras.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                        Belum ada penyelenggara. Tambahkan penyelenggara dulu di
                        menu Penyelenggara, lalu kembali ke sini.
                    </p>
                ) : (
                    <Form
                        {...store.form()}
                        onSuccess={() => onOpenChange(false)}
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="penyelenggara_id">
                                        Penyelenggara
                                    </Label>
                                    <select
                                        id="penyelenggara_id"
                                        name="penyelenggara_id"
                                        required
                                        defaultValue={penyelenggaraAwal}
                                        className="h-9 rounded-md border bg-background px-2 text-sm"
                                    >
                                        <option value="" disabled>
                                            Pilih penyelenggara…
                                        </option>
                                        {penyelenggaras.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.penyelenggara_id}
                                    />
                                </div>

                                <fieldset className="grid gap-1.5">
                                    <legend className="mb-1.5 text-sm font-medium">
                                        Template
                                    </legend>
                                    <input
                                        type="hidden"
                                        name="template"
                                        value={template}
                                    />
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {templates.map((t) => (
                                            <button
                                                key={t.key}
                                                type="button"
                                                onClick={() =>
                                                    setTemplate(t.key)
                                                }
                                                aria-pressed={
                                                    template === t.key
                                                }
                                                className={cn(
                                                    'rounded-lg border p-3 text-left transition-colors hover:bg-muted/50',
                                                    template === t.key &&
                                                        'border-primary ring-2 ring-primary/30',
                                                )}
                                            >
                                                <span className="block text-sm font-medium">
                                                    {t.name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {t.istilah_peserta} ·{' '}
                                                    {t.istilah_grup}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                    <InputError message={errors.template} />
                                </fieldset>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="name">
                                        Nama kategori (opsional)
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        maxLength={120}
                                        placeholder="Kosongkan untuk memakai nama template"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => onOpenChange(false)}
                                    >
                                        Batal
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={processing || !template}
                                    >
                                        Buat kategori
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
