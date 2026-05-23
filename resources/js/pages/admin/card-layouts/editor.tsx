import { Head, router, useForm } from '@inertiajs/react';
import { Eye, Loader2, Save } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';

type FrameItem = {
    id: string;
    name: string;
    image_url: string;
    width: number;
    height: number;
};

type LayoutData = {
    id?: string;
    name: string;
    type: string;
    is_default: boolean;
    is_active: boolean;
    layout_config: Record<string, unknown>;
} | null;

type Props = {
    layout: LayoutData;
    frames: FrameItem[];
};

const defaultConfig: Record<string, unknown> = {
    card_width: 638,
    card_height: 1011,
    bg_color: '#ffffff',
    padding: '40px 30px',
    logo_size: 60,
    school_name_size: 16,
    school_name_color: '#1a1a2e',
    card_type_size: 14,
    card_type_color: '#4a4a8a',
    photo_size: 180,
    photo_radius: 12,
    photo_border_color: '#3b82f6',
    photo_margin: '16px 0',
    name_size: 22,
    name_color: '#1a1a2e',
    info_size: 13,
    qr_size: 100,
    show_qr: true,
    show_address: false,
    show_watermark: false,
    watermark_text: '',
    header_margin: 20,
    frame_id: null,
};

export default function CardLayoutEditor({ layout, frames }: Props) {
    const isEditing = !!layout?.id;
    const existingConfig = layout?.layout_config ?? {};
    const mergedConfig = { ...defaultConfig, ...existingConfig };

    const form = useForm({
        name: layout?.name ?? '',
        type: layout?.type ?? 'osis',
        is_default: layout?.is_default ?? false,
        is_active: layout?.is_active ?? true,
        layout_config: mergedConfig,
    });

    function updateConfig(key: string, value: unknown) {
        form.setData('layout_config', { ...form.data.layout_config, [key]: value });
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        if (isEditing) {
            form.put(`/admin/card-layouts/${layout!.id}`, { preserveScroll: true });
        } else {
            form.post('/admin/card-layouts', { preserveScroll: true });
        }
    }

    const config = form.data.layout_config;
    const selectedFrame = frames.find((f) => f.id === config.frame_id);

    return (
        <>
            <Head title={isEditing ? 'Edit Layout Kartu' : 'Buat Layout Kartu'} />
            <form onSubmit={handleSubmit} className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEditing ? 'Edit Layout Kartu' : 'Buat Layout Kartu Baru'}
                    </h1>
                    <Button type="submit" disabled={form.processing} className="gap-2">
                        {form.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                        Simpan Layout
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_380px]">
                    {/* Live Preview */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Eye className="size-4" /> Preview Kartu
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex justify-center">
                                <div
                                    className="relative overflow-hidden rounded-lg border shadow-lg"
                                    style={{
                                        width: Math.min(Number(config.card_width) || 638, 380),
                                        height: Math.min(Number(config.card_width) || 638, 380) * ((Number(config.card_height) || 1011) / (Number(config.card_width) || 638)),
                                        background: String(config.bg_color || '#fff'),
                                    }}
                                >
                                    {/* Frame overlay */}
                                    {selectedFrame && (
                                        <img
                                            src={selectedFrame.image_url}
                                            alt="Frame"
                                            className="pointer-events-none absolute inset-0 z-10 size-full object-contain"
                                        />
                                    )}

                                    {/* Card content preview */}
                                    <div className="relative z-20 flex size-full flex-col items-center p-[6%]">
                                        <div className="mb-2 text-center">
                                            <div
                                                className="mx-auto mb-1 size-[10%] rounded-full bg-blue-100"
                                                style={{ minWidth: 24, minHeight: 24 }}
                                            />
                                            <p
                                                className="font-bold uppercase tracking-wide"
                                                style={{
                                                    fontSize: `${Math.max(Number(config.school_name_size) || 16, 8) * 0.6}px`,
                                                    color: String(config.school_name_color),
                                                }}
                                            >
                                                NAMA SEKOLAH
                                            </p>
                                            <p
                                                className="font-semibold uppercase tracking-widest"
                                                style={{
                                                    fontSize: `${Math.max(Number(config.card_type_size) || 14, 8) * 0.6}px`,
                                                    color: String(config.card_type_color),
                                                }}
                                            >
                                                {form.data.type === 'osis' ? 'KARTU OSIS' : form.data.type === 'perpustakaan' ? 'KARTU PERPUSTAKAAN' : 'KARTU IDENTITAS'}
                                            </p>
                                        </div>

                                        <div
                                            className="my-2 bg-zinc-200"
                                            style={{
                                                width: `${Math.max(Number(config.photo_size) || 180, 40) * 0.55}px`,
                                                height: `${Math.max(Number(config.photo_size) || 180, 40) * 0.55 * 1.3}px`,
                                                borderRadius: `${Number(config.photo_radius) * 0.6}px`,
                                                border: `2px solid ${config.photo_border_color}`,
                                            }}
                                        />

                                        <p
                                            className="font-bold"
                                            style={{
                                                fontSize: `${Math.max(Number(config.name_size) || 22, 8) * 0.55}px`,
                                                color: String(config.name_color),
                                            }}
                                        >
                                            NAMA SISWA
                                        </p>

                                        <div className="mt-1 w-4/5 space-y-0.5">
                                            {['NIS', 'NISN', 'Kelas'].map((label) => (
                                                <div key={label} className="flex border-b border-zinc-100 py-0.5" style={{ fontSize: `${Math.max(Number(config.info_size) || 13, 8) * 0.55}px` }}>
                                                    <span className="w-2/5 text-zinc-400">{label}</span>
                                                    <span className="w-3/5 font-medium text-zinc-700">xxxxxxx</span>
                                                </div>
                                            ))}
                                        </div>

                                        {config.show_qr && (
                                            <div className="mt-auto">
                                                <div
                                                    className="rounded border bg-white p-1"
                                                    style={{
                                                        width: `${Math.max(Number(config.qr_size) || 100, 30) * 0.5}px`,
                                                        height: `${Math.max(Number(config.qr_size) || 100, 30) * 0.5}px`,
                                                    }}
                                                >
                                                    <div className="grid size-full grid-cols-5 grid-rows-5 gap-px">
                                                        {Array.from({ length: 25 }).map((_, i) => (
                                                            <div key={i} className={i % 3 === 0 ? 'bg-zinc-800' : 'bg-white'} />
                                                        ))}
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Settings Panel */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader><CardTitle className="text-base">Informasi Layout</CardTitle></CardHeader>
                            <CardContent className="space-y-3">
                                <div className="grid gap-1.5">
                                    <Label>Nama Layout</Label>
                                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Kartu OSIS Biru" />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label>Jenis Kartu</Label>
                                    <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="osis">Kartu OSIS</SelectItem>
                                            <SelectItem value="perpustakaan">Kartu Perpustakaan</SelectItem>
                                            <SelectItem value="identitas">Kartu Identitas</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox checked={form.data.is_default} onCheckedChange={(c) => form.setData('is_default', c === true)} id="is_default" />
                                    <Label htmlFor="is_default">Jadikan default</Label>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle className="text-base">Frame</CardTitle></CardHeader>
                            <CardContent>
                                <Select value={String(config.frame_id ?? 'none')} onValueChange={(v) => updateConfig('frame_id', v === 'none' ? null : v)}>
                                    <SelectTrigger><SelectValue placeholder="Tanpa frame" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Tanpa Frame</SelectItem>
                                        {frames.map((f) => (
                                            <SelectItem key={f.id} value={String(f.id)}>{f.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle className="text-base">Ukuran & Warna</CardTitle></CardHeader>
                            <CardContent className="space-y-3">
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="grid gap-1.5">
                                        <Label className="text-xs">Lebar (px)</Label>
                                        <Input type="number" value={String(config.card_width)} onChange={(e) => updateConfig('card_width', Number(e.target.value))} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label className="text-xs">Tinggi (px)</Label>
                                        <Input type="number" value={String(config.card_height)} onChange={(e) => updateConfig('card_height', Number(e.target.value))} />
                                    </div>
                                </div>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">Background</Label>
                                    <div className="flex gap-2">
                                        <input type="color" value={String(config.bg_color)} onChange={(e) => updateConfig('bg_color', e.target.value)} className="h-9 w-12 cursor-pointer rounded border" />
                                        <Input value={String(config.bg_color)} onChange={(e) => updateConfig('bg_color', e.target.value)} className="flex-1" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle className="text-base">Tipografi</CardTitle></CardHeader>
                            <CardContent className="space-y-3">
                                {[
                                    { key: 'school_name_size', label: 'Nama Sekolah' },
                                    { key: 'card_type_size', label: 'Tipe Kartu' },
                                    { key: 'name_size', label: 'Nama Siswa' },
                                    { key: 'info_size', label: 'Info Detail' },
                                ].map(({ key, label }) => (
                                    <div key={key} className="grid grid-cols-2 items-center gap-2">
                                        <Label className="text-xs">{label}</Label>
                                        <Input type="number" value={String(config[key])} onChange={(e) => updateConfig(key, Number(e.target.value))} min={8} max={48} />
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle className="text-base">Foto & QR</CardTitle></CardHeader>
                            <CardContent className="space-y-3">
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="grid gap-1.5">
                                        <Label className="text-xs">Ukuran Foto</Label>
                                        <Input type="number" value={String(config.photo_size)} onChange={(e) => updateConfig('photo_size', Number(e.target.value))} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label className="text-xs">Ukuran QR</Label>
                                        <Input type="number" value={String(config.qr_size)} onChange={(e) => updateConfig('qr_size', Number(e.target.value))} />
                                    </div>
                                </div>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">Border Foto</Label>
                                    <div className="flex gap-2">
                                        <input type="color" value={String(config.photo_border_color)} onChange={(e) => updateConfig('photo_border_color', e.target.value)} className="h-9 w-12 cursor-pointer rounded border" />
                                        <Input value={String(config.photo_border_color)} onChange={(e) => updateConfig('photo_border_color', e.target.value)} className="flex-1" />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Checkbox checked={config.show_qr as boolean} onCheckedChange={(c) => updateConfig('show_qr', c === true)} id="show_qr" />
                                        <Label htmlFor="show_qr" className="text-xs">Tampilkan QR Code</Label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Checkbox checked={config.show_address as boolean} onCheckedChange={(c) => updateConfig('show_address', c === true)} id="show_address" />
                                        <Label htmlFor="show_address" className="text-xs">Tampilkan Alamat</Label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Checkbox checked={config.show_watermark as boolean} onCheckedChange={(c) => updateConfig('show_watermark', c === true)} id="show_watermark" />
                                        <Label htmlFor="show_watermark" className="text-xs">Watermark</Label>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </>
    );
}

CardLayoutEditor.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Layout Kartu', href: '/admin/card-layouts' },
        { title: 'Editor' },
    ],
};
