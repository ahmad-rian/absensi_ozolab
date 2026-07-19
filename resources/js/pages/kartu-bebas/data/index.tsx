import { Head, router, useForm } from '@inertiajs/react';
import { Database, ExternalLink, Image as ImageIcon, Loader2, Pencil, Plus, Sparkles, Trash2 } from 'lucide-react';
import { type FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';

type FieldDef = {
    key: string;
    label: string;
    type: 'text' | 'date' | 'number' | 'select' | 'photo';
    required?: boolean;
    options?: string[];
};

type LayoutItem = { id: string; name: string; fields: FieldDef[] };

type RecordItem = {
    id: string;
    data: Record<string, string | number | null>;
    photo_url: string | null;
    status: string;
    card_url: string | null;
    created_at: string | null;
};

type Props = {
    layouts: LayoutItem[];
    layout: LayoutItem | null;
    records: RecordItem[];
};

type FormValues = Record<string, string | File | null>;

function buildInitialValues(fields: FieldDef[], record?: RecordItem): FormValues {
    const values: FormValues = {};
    for (const f of fields) {
        if (f.type === 'photo') {
            values[f.key] = null;
        } else {
            const v = record?.data?.[f.key];
            values[f.key] = v === null || v === undefined ? '' : String(v);
        }
    }
    return values;
}

export default function KartuBebasDataIndex({ layouts, layout, records }: Props) {
    const fields = useMemo(() => layout?.fields ?? [], [layout]);
    const columnFields = useMemo(() => fields.filter((f) => f.type !== 'photo'), [fields]);
    const photoField = useMemo(() => fields.find((f) => f.type === 'photo') ?? null, [fields]);

    const [showAdd, setShowAdd] = useState(false);
    const [editingRecord, setEditingRecord] = useState<RecordItem | null>(null);
    const [processingId, setProcessingId] = useState<string | null>(null);

    const addFileRef = useRef<HTMLInputElement>(null);
    const editFileRef = useRef<HTMLInputElement>(null);

    const addForm = useForm<FormValues>(buildInitialValues(fields));
    const editForm = useForm<FormValues>(buildInitialValues(fields));

    const hasProcessing = records.some((record) => record.status === 'processing');

    useEffect(() => {
        if (!hasProcessing) {
            return;
        }

        let reloading = false;
        const interval = window.setInterval(() => {
            if (reloading) {
                return;
            }
            reloading = true;
            router.reload({
                only: ['records'],
                onFinish: () => {
                    reloading = false;
                },
            });
        }, 3000);

        return () => window.clearInterval(interval);
    }, [hasProcessing]);

    function onLayoutChange(id: string) {
        router.get('/kartu-bebas/data', { layout_id: id }, { preserveState: false, preserveScroll: true });
    }

    function appendData(fd: FormData, values: FormValues) {
        for (const f of fields) {
            const val = values[f.key];
            if (f.type === 'photo') {
                if (val instanceof File) fd.append(`data[${f.key}]`, val);
            } else {
                fd.append(`data[${f.key}]`, val == null ? '' : String(val));
            }
        }
    }

    function openAdd() {
        addForm.setData(buildInitialValues(fields));
        if (addFileRef.current) addFileRef.current.value = '';
        setShowAdd(true);
    }

    function handleAdd(e: FormEvent) {
        e.preventDefault();
        if (!layout) return;
        const fd = new FormData();
        fd.append('layout_id', layout.id);
        appendData(fd, addForm.data);

        router.post('/kartu-bebas/data', fd, {
            preserveScroll: true,
            onSuccess: () => {
                setShowAdd(false);
                addForm.reset();
                if (addFileRef.current) addFileRef.current.value = '';
            },
            onError: (errors) => addForm.setError(errors as Record<string, string>),
        });
    }

    function openEdit(record: RecordItem) {
        setEditingRecord(record);
        editForm.setData(buildInitialValues(fields, record));
        if (editFileRef.current) editFileRef.current.value = '';
    }

    function handleEdit(e: FormEvent) {
        e.preventDefault();
        if (!editingRecord) return;
        const fd = new FormData();
        fd.append('_method', 'put');
        appendData(fd, editForm.data);

        router.post(`/kartu-bebas/data/${editingRecord.id}`, fd, {
            preserveScroll: true,
            onSuccess: () => setEditingRecord(null),
            onError: (errors) => editForm.setError(errors as Record<string, string>),
        });
    }

    function handleGenerate(record: RecordItem) {
        setProcessingId(record.id);
        router.post(
            `/kartu-bebas/data/${record.id}/generate`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessingId(null),
            },
        );
    }

    function handleDelete(record: RecordItem) {
        if (!confirm('Hapus data ini?')) return;
        router.delete(`/kartu-bebas/data/${record.id}`, { preserveScroll: true });
    }

    function renderField(form: ReturnType<typeof useForm<FormValues>>, fileRef: React.RefObject<HTMLInputElement | null>, field: FieldDef) {
        const value = form.data[field.key];
        const error = form.errors[`data.${field.key}` as keyof typeof form.errors] as string | undefined;

        return (
            <div key={field.key} className="grid gap-2">
                <Label>
                    {field.label}
                    {field.required && <span className="text-red-500"> *</span>}
                </Label>
                {field.type === 'photo' ? (
                    <Input
                        ref={fileRef}
                        type="file"
                        accept="image/*"
                        onChange={(e) => form.setData(field.key, e.target.files?.[0] || null)}
                    />
                ) : field.type === 'select' ? (
                    <Select value={(value as string) || ''} onValueChange={(v) => form.setData(field.key, v)}>
                        <SelectTrigger>
                            <SelectValue placeholder={`Pilih ${field.label}`} />
                        </SelectTrigger>
                        <SelectContent>
                            {(field.options ?? []).map((opt) => (
                                <SelectItem key={opt} value={opt}>
                                    {opt}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                ) : (
                    <Input
                        type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'}
                        value={(value as string) ?? ''}
                        onChange={(e) => form.setData(field.key, e.target.value)}
                    />
                )}
                <InputError message={error} />
            </div>
        );
    }

    return (
        <>
            <Head title="Data Kartu" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">Data Kartu</h1>
                        <p className="text-muted-foreground text-sm">Masukkan data isian per layout, lalu generate kartunya.</p>
                        {hasProcessing && (
                            <span className="text-muted-foreground mt-1 inline-flex items-center gap-1 text-xs">
                                <Loader2 className="size-3 animate-spin" /> memperbarui…
                            </span>
                        )}
                    </div>
                    <Button onClick={openAdd} disabled={!layout} className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                        <Plus className="size-4" /> Tambah Data
                    </Button>
                </div>

                <div className="flex items-center gap-3">
                    <Label className="text-sm">Layout</Label>
                    <Select value={layout?.id ?? ''} onValueChange={onLayoutChange}>
                        <SelectTrigger className="w-64">
                            <SelectValue placeholder="Pilih layout" />
                        </SelectTrigger>
                        <SelectContent>
                            {layouts.map((l) => (
                                <SelectItem key={l.id} value={l.id}>
                                    {l.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {!layout ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <Database className="mb-4 size-12 text-emerald-500/70" />
                            <p className="text-muted-foreground text-sm">Belum ada layout. Buat layout kartu terlebih dahulu.</p>
                        </CardContent>
                    </Card>
                ) : records.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <Database className="mb-4 size-12 text-emerald-500/70" />
                            <p className="text-muted-foreground text-sm">Belum ada data untuk layout ini. Klik "Tambah Data".</p>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="overflow-x-auto p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {photoField && <TableHead>Foto</TableHead>}
                                        {columnFields.map((f) => (
                                            <TableHead key={f.key}>{f.label}</TableHead>
                                        ))}
                                        <TableHead>Status</TableHead>
                                        <TableHead>Kartu</TableHead>
                                        <TableHead className="text-right">Aksi</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {records.map((record) => (
                                        <TableRow key={record.id}>
                                            {photoField && (
                                                <TableCell>
                                                    {record.photo_url ? (
                                                        <img src={record.photo_url} alt="foto" className="size-10 rounded object-cover" />
                                                    ) : (
                                                        <div className="bg-muted flex size-10 items-center justify-center rounded">
                                                            <ImageIcon className="text-muted-foreground size-4" />
                                                        </div>
                                                    )}
                                                </TableCell>
                                            )}
                                            {columnFields.map((f) => (
                                                <TableCell key={f.key}>{record.data?.[f.key] ?? '-'}</TableCell>
                                            ))}
                                            <TableCell>
                                                {record.status === 'completed' ? (
                                                    <Badge className="bg-emerald-600 text-white">Selesai</Badge>
                                                ) : record.status === 'processing' ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="gap-1 border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-800 dark:bg-amber-900 dark:text-amber-300"
                                                    >
                                                        <Loader2 className="size-3 animate-spin" /> Proses
                                                    </Badge>
                                                ) : record.status === 'failed' ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-red-200 bg-red-100 text-red-800 dark:border-red-800 dark:bg-red-900 dark:text-red-300"
                                                    >
                                                        Gagal
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">Draft</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {record.card_url ? (
                                                    <a
                                                        href={record.card_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-1 text-emerald-600 hover:underline dark:text-emerald-400"
                                                    >
                                                        Lihat <ExternalLink className="size-3" />
                                                    </a>
                                                ) : (
                                                    <span className="text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="gap-1 border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-300"
                                                        onClick={() => handleGenerate(record)}
                                                        disabled={processingId === record.id}
                                                    >
                                                        {processingId === record.id ? (
                                                            <Loader2 className="size-3 animate-spin" />
                                                        ) : (
                                                            <Sparkles className="size-3" />
                                                        )}
                                                        Generate
                                                    </Button>
                                                    <Button variant="ghost" size="icon" onClick={() => openEdit(record)}>
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button variant="ghost" size="icon" onClick={() => handleDelete(record)}>
                                                        <Trash2 className="size-4 text-red-500" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Add Dialog */}
            <Dialog open={showAdd} onOpenChange={setShowAdd}>
                <DialogContent className="max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Tambah Data</DialogTitle>
                        <DialogDescription>Isi data untuk layout "{layout?.name}".</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleAdd} className="space-y-4">
                        {fields.map((f) => renderField(addForm, addFileRef, f))}
                        <DialogFooter>
                            <Button type="submit" disabled={addForm.processing} className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                                {addForm.processing && <Loader2 className="size-4 animate-spin" />}
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={!!editingRecord} onOpenChange={(open) => !open && setEditingRecord(null)}>
                <DialogContent className="max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit Data</DialogTitle>
                        {photoField && <DialogDescription>Kosongkan input foto untuk mempertahankan foto lama.</DialogDescription>}
                    </DialogHeader>
                    <form onSubmit={handleEdit} className="space-y-4">
                        {fields.map((f) => renderField(editForm, editFileRef, f))}
                        <DialogFooter>
                            <Button type="submit" disabled={editForm.processing} className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                                {editForm.processing && <Loader2 className="size-4 animate-spin" />}
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

KartuBebasDataIndex.layout = (page: React.ReactNode) => (
    <KartuBebasLayout
        breadcrumbs={[
            { title: 'Dashboard', href: '/kartu-bebas' },
            { title: 'Data', href: '/kartu-bebas/data' },
        ]}
    >
        {page}
    </KartuBebasLayout>
);
