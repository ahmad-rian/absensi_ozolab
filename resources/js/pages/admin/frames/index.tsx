import { Head, router, useForm } from '@inertiajs/react';
import { Frame, ImagePlus, Loader2, Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

type FrameItem = {
    id: number;
    name: string;
    image_url: string;
    width: number;
    height: number;
    category: string;
    is_active: boolean;
    sort_order: number;
};

type Props = {
    frames: FrameItem[];
    filters: { category: string };
};

export default function FramesIndex({ frames, filters }: Props) {
    const [showAdd, setShowAdd] = useState(false);
    const [editingFrame, setEditingFrame] = useState<FrameItem | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const addForm = useForm<{ name: string; image: File | null; category: string }>({
        name: '',
        image: null,
        category: 'card',
    });

    const editForm = useForm<{ name: string; category: string; is_active: boolean; sort_order: number }>({
        name: '',
        category: 'card',
        is_active: true,
        sort_order: 0,
    });

    function handleAdd(e: FormEvent) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('name', addForm.data.name);
        formData.append('category', addForm.data.category);
        if (addForm.data.image) formData.append('image', addForm.data.image);

        router.post('/admin/frames', formData, {
            preserveScroll: true,
            onSuccess: () => {
                setShowAdd(false);
                addForm.reset();
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    }

    function openEdit(frame: FrameItem) {
        setEditingFrame(frame);
        editForm.setData({
            name: frame.name,
            category: frame.category,
            is_active: frame.is_active,
            sort_order: frame.sort_order,
        });
    }

    function handleEdit(e: FormEvent) {
        e.preventDefault();
        if (!editingFrame) return;
        editForm.put(`/admin/frames/${editingFrame.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditingFrame(null),
        });
    }

    function handleDelete(frame: FrameItem) {
        if (!confirm(`Hapus frame "${frame.name}"?`)) return;
        router.delete(`/admin/frames/${frame.id}`, { preserveScroll: true });
    }

    function handleCategoryFilter(val: string) {
        router.get('/admin/frames', val && val !== 'all' ? { category: val } : {}, { preserveState: true });
    }

    return (
        <>
            <Head title="Frame & Bingkai" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Frame & Bingkai</h1>
                        <p className="text-muted-foreground text-sm">Kelola frame untuk kartu siswa dan album foto.</p>
                    </div>
                    <Button onClick={() => setShowAdd(true)} className="gap-2">
                        <Plus className="size-4" /> Tambah Frame
                    </Button>
                </div>

                {/* Filter */}
                <div className="flex items-center gap-3">
                    <Select value={filters.category || 'all'} onValueChange={handleCategoryFilter}>
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Semua kategori" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua</SelectItem>
                            <SelectItem value="card">Kartu Siswa</SelectItem>
                            <SelectItem value="album">Album Foto</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Frame Grid */}
                {frames.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <Frame className="text-muted-foreground mb-4 size-12" />
                            <p className="text-muted-foreground text-sm">Belum ada frame. Tambahkan frame pertama.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {frames.map((frame) => (
                            <Card key={frame.id} className={!frame.is_active ? 'opacity-50' : ''}>
                                <div className="relative aspect-[3/4] overflow-hidden rounded-t-lg bg-zinc-100 dark:bg-zinc-800">
                                    <img src={frame.image_url} alt={frame.name} className="size-full object-contain p-2" />
                                    <div className="absolute right-2 top-2 flex gap-1">
                                        <Badge variant={frame.category === 'card' ? 'default' : 'secondary'}>
                                            {frame.category === 'card' ? 'Kartu' : 'Album'}
                                        </Badge>
                                    </div>
                                </div>
                                <CardContent className="p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="font-medium">{frame.name}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {frame.width} x {frame.height}px
                                                {!frame.is_active && ' • Nonaktif'}
                                            </p>
                                        </div>
                                        <div className="flex gap-1">
                                            <Button variant="ghost" size="icon" onClick={() => openEdit(frame)}>
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon" onClick={() => handleDelete(frame)}>
                                                <Trash2 className="size-4 text-red-500" />
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Add Dialog */}
            <Dialog open={showAdd} onOpenChange={setShowAdd}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tambah Frame Baru</DialogTitle>
                        <DialogDescription>Upload gambar frame/bingkai untuk kartu atau album.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleAdd} className="space-y-4">
                        <div className="grid gap-2">
                            <Label>Nama Frame</Label>
                            <Input
                                value={addForm.data.name}
                                onChange={(e) => addForm.setData('name', e.target.value)}
                                placeholder="Contoh: Frame OSIS Biru"
                            />
                            <InputError message={addForm.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Kategori</Label>
                            <Select value={addForm.data.category} onValueChange={(v) => addForm.setData('category', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="card">Kartu Siswa</SelectItem>
                                    <SelectItem value="album">Album Foto</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Gambar Frame</Label>
                            <Input
                                ref={fileInputRef}
                                type="file"
                                accept="image/*"
                                onChange={(e) => addForm.setData('image', e.target.files?.[0] || null)}
                            />
                            <p className="text-muted-foreground text-xs">PNG transparan direkomendasikan. Maks 5MB.</p>
                            <InputError message={addForm.errors.image} />
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={addForm.processing} className="gap-2">
                                {addForm.processing ? <Loader2 className="size-4 animate-spin" /> : <ImagePlus className="size-4" />}
                                Upload Frame
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={!!editingFrame} onOpenChange={(open) => !open && setEditingFrame(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Frame</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleEdit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label>Nama Frame</Label>
                            <Input
                                value={editForm.data.name}
                                onChange={(e) => editForm.setData('name', e.target.value)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Kategori</Label>
                            <Select value={editForm.data.category} onValueChange={(v) => editForm.setData('category', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="card">Kartu Siswa</SelectItem>
                                    <SelectItem value="album">Album Foto</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Urutan</Label>
                            <Input
                                type="number"
                                value={editForm.data.sort_order}
                                onChange={(e) => editForm.setData('sort_order', Number(e.target.value))}
                                min={0}
                            />
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={editForm.processing}>Simpan</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

FramesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Frame & Bingkai', href: '/admin/frames' },
    ],
};
