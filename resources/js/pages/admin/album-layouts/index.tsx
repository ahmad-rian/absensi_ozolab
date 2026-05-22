import { Head, router, useForm } from '@inertiajs/react';
import { BookOpen, Loader2, Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';

type AlbumLayout = {
    id: number;
    name: string;
    paper_size: string;
    orientation: string;
    columns: number;
    rows: number;
    is_default: boolean;
    is_active: boolean;
    layout_config: Record<string, unknown>;
};

type Props = { layouts: AlbumLayout[] };

const defaultConfig: Record<string, unknown> = {
    bg_color: '#ffffff',
    page_padding: '30px',
    header_size: 18,
    header_color: '#1a1a2e',
    header_border_color: '#3b82f6',
    grid_gap: 12,
    cell_padding: '8px',
    cell_border_color: '#e5e7eb',
    cell_bg: '#fafafa',
    cell_radius: 8,
    photo_size: 80,
    photo_radius: 6,
    photo_border_color: '#cbd5e1',
    name_size: 11,
    info_size: 9,
    title: '',
    subtitle: 'Album Foto Siswa',
};

export default function AlbumLayoutsIndex({ layouts }: Props) {
    const [showDialog, setShowDialog] = useState(false);
    const [editingLayout, setEditingLayout] = useState<AlbumLayout | null>(null);

    const form = useForm({
        name: '',
        paper_size: 'A4',
        orientation: 'portrait',
        columns: 3,
        rows: 4,
        is_default: false,
        is_active: true,
        layout_config: { ...defaultConfig },
    });

    function openCreate() {
        setEditingLayout(null);
        form.reset();
        form.setData('layout_config', { ...defaultConfig });
        setShowDialog(true);
    }

    function openEdit(layout: AlbumLayout) {
        setEditingLayout(layout);
        form.setData({
            name: layout.name,
            paper_size: layout.paper_size,
            orientation: layout.orientation,
            columns: layout.columns,
            rows: layout.rows,
            is_default: layout.is_default,
            is_active: layout.is_active,
            layout_config: { ...defaultConfig, ...layout.layout_config },
        });
        setShowDialog(true);
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        if (editingLayout) {
            form.put(`/admin/album-layouts/${editingLayout.id}`, {
                preserveScroll: true,
                onSuccess: () => setShowDialog(false),
            });
        } else {
            form.post('/admin/album-layouts', {
                preserveScroll: true,
                onSuccess: () => setShowDialog(false),
            });
        }
    }

    function handleDelete(layout: AlbumLayout) {
        if (!confirm(`Hapus layout "${layout.name}"?`)) return;
        router.delete(`/admin/album-layouts/${layout.id}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Layout Album" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Layout Album Foto</h1>
                        <p className="text-muted-foreground text-sm">Kelola layout halaman album foto siswa.</p>
                    </div>
                    <Button onClick={openCreate} className="gap-2">
                        <Plus className="size-4" /> Buat Layout
                    </Button>
                </div>

                {layouts.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <BookOpen className="text-muted-foreground mb-4 size-12" />
                            <p className="text-muted-foreground text-sm">Belum ada layout album.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {layouts.map((layout) => (
                            <Card key={layout.id} className={!layout.is_active ? 'opacity-50' : ''}>
                                <CardContent className="p-5">
                                    <div className="mb-3 flex items-start justify-between">
                                        <h3 className="font-semibold">{layout.name}</h3>
                                        <div className="flex gap-1">
                                            {layout.is_default && <Badge className="bg-blue-100 text-blue-800">Default</Badge>}
                                        </div>
                                    </div>
                                    <div className="text-muted-foreground mb-4 space-y-1 text-sm">
                                        <p>{layout.paper_size} • {layout.orientation === 'portrait' ? 'Potrait' : 'Landscape'}</p>
                                        <p>{layout.columns} kolom x {layout.rows} baris = {layout.columns * layout.rows} siswa/halaman</p>
                                    </div>

                                    {/* Mini preview */}
                                    <div className="mb-4 rounded border bg-zinc-50 p-2 dark:bg-zinc-900">
                                        <div
                                            className="mx-auto grid gap-1"
                                            style={{
                                                gridTemplateColumns: `repeat(${layout.columns}, 1fr)`,
                                                maxWidth: layout.orientation === 'landscape' ? '100%' : '70%',
                                                aspectRatio: layout.orientation === 'landscape' ? '1.414/1' : '1/1.414',
                                            }}
                                        >
                                            {Array.from({ length: Math.min(layout.columns * layout.rows, 20) }).map((_, i) => (
                                                <div key={i} className="aspect-[3/4] rounded-sm bg-zinc-200 dark:bg-zinc-700" />
                                            ))}
                                        </div>
                                    </div>

                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" className="flex-1" onClick={() => openEdit(layout)}>
                                            <Pencil className="mr-1 size-3" /> Edit
                                        </Button>
                                        <Button variant="ghost" size="sm" onClick={() => handleDelete(layout)}>
                                            <Trash2 className="size-4 text-red-500" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={showDialog} onOpenChange={setShowDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingLayout ? 'Edit' : 'Buat'} Layout Album</DialogTitle>
                        <DialogDescription>Atur ukuran kertas, kolom, dan baris per halaman.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label>Nama Layout</Label>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Album A4 3x4" />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label>Ukuran Kertas</Label>
                                <Select value={form.data.paper_size} onValueChange={(v) => form.setData('paper_size', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="A4">A4</SelectItem>
                                        <SelectItem value="A3">A3</SelectItem>
                                        <SelectItem value="Letter">Letter</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label>Orientasi</Label>
                                <Select value={form.data.orientation} onValueChange={(v) => form.setData('orientation', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="portrait">Portrait</SelectItem>
                                        <SelectItem value="landscape">Landscape</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label>Kolom</Label>
                                <Input type="number" value={form.data.columns} onChange={(e) => form.setData('columns', Number(e.target.value))} min={1} max={10} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Baris</Label>
                                <Input type="number" value={form.data.rows} onChange={(e) => form.setData('rows', Number(e.target.value))} min={1} max={10} />
                            </div>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Total: {form.data.columns * form.data.rows} siswa per halaman
                        </p>
                        <DialogFooter>
                            <Button type="submit" disabled={form.processing} className="gap-2">
                                {form.processing && <Loader2 className="size-4 animate-spin" />}
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

AlbumLayoutsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Layout Album', href: '/admin/album-layouts' },
    ],
};
