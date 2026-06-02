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
    category: string;
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
    // ATM card dimensions (85.6 x 54 mm landscape)
    card_width: 813,
    card_height: 513,
    // Header band
    header_gradient_start: '#5dc4f5',
    header_gradient_end: '#3aa8df',
    header_text_color: '#06243a',
    // Watermark
    watermark_text: 'ORGANISASI SISWA INTRA SEKOLAH',
    show_emblem: true,
    show_validity: true,
    validity_text: 'BERLAKU S/D TAMAT BELAJAR',
    // Photo & QR
    photo_width_mm: 16,
    photo_height_mm: 21,
    qr_size_mm: 15,
    show_qr: true,
    show_signature: true,
    // Typography
    font_family: 'Manrope',
    font_school: 16,
    font_field: 15,
    // Frame
    frame_id: null,
};

// 1mm = 5.607px at 480px card width (480 / 85.6)
const S = 5.607;

function CardPreview({ config, type }: { config: Record<string, unknown>; type: string }) {
    const isOsis = type === 'osis';
    return (
        <div
            className="relative overflow-hidden rounded-lg shadow-lg"
            style={{
                width: 480,
                height: Math.round(54 * S),
                background: isOsis
                    ? 'radial-gradient(ellipse at 70% 30%, rgba(255,255,255,0.5), transparent 60%), linear-gradient(135deg, #e0f3ff, #c7e9fb, #b8e1f7)'
                    : 'radial-gradient(ellipse at 30% 20%, rgba(255,255,255,0.4), transparent 50%), linear-gradient(135deg, #d6b88a, #e8d2a8, #d4b380)',
            }}
        >
            {/* Header band — 13mm */}
            <div
                className="flex items-center px-[11px]"
                style={{
                    height: Math.round(13 * S),
                    gap: Math.round(1.2 * S),
                    background: `linear-gradient(180deg, ${config.header_gradient_start}, ${config.header_gradient_end})`,
                    color: String(config.header_text_color),
                }}
            >
                <div className="shrink-0 rounded-full bg-white/25" style={{ width: Math.round(8.5 * S), height: Math.round(8.5 * S) }} />
                <div className="min-w-0 flex-1 text-center leading-none" style={{ fontFamily: "'Inter Tight', sans-serif" }}>
                    <div style={{ fontSize: Math.round(1.2 * S), fontWeight: 700 }}>PEMERINTAH KABUPATEN</div>
                    <div style={{ fontSize: Math.round(1.3 * S), fontWeight: 700, letterSpacing: '0.02em' }}>DINAS PENDIDIKAN</div>
                    <div style={{ fontSize: Math.round(2.0 * S), fontWeight: 800, marginTop: Math.round(0.3 * S) }}>NAMA SEKOLAH</div>
                    <div style={{ fontSize: Math.round(1.0 * S), fontWeight: 500, marginTop: Math.round(0.3 * S), opacity: 0.85 }}>Jl. Pendidikan No. 1</div>
                </div>
                <div className="shrink-0 rounded-full bg-white/25" style={{ width: Math.round(8.5 * S), height: Math.round(8.5 * S) }} />
            </div>

            {/* Watermark */}
            <div className="absolute inset-0 overflow-hidden" style={{ top: Math.round(13 * S), opacity: 0.35 }}>
                {Array.from({ length: 8 }).map((_, i) => (
                    <div key={i} className="flex whitespace-nowrap" style={{
                        fontFamily: "'Inter Tight', sans-serif", fontWeight: 800,
                        fontSize: Math.round(1.6 * S), lineHeight: '1.6',
                        color: isOsis ? 'rgba(255,255,255,0.85)' : 'rgba(255,255,255,0.5)',
                        transform: i % 2 ? `translateX(${Math.round(-3 * S)}px)` : undefined,
                    }}>
                        {Array.from({ length: 4 }).map((_, j) => (
                            <span key={j} style={{ paddingRight: Math.round(1.2 * S) }}>{String(config.watermark_text || 'WATERMARK')}</span>
                        ))}
                    </div>
                ))}
            </div>

            {/* Body fields — 1.4mm font */}
            <div className="relative z-10" style={{ padding: `${Math.round(1.5 * S)}px ${Math.round(2.5 * S)}px 0` }}>
                {['NAMA', 'ALAMAT', 'TEMPAT TGL.LAHIR', 'AGAMA', 'NO.INDUK'].map((label) => (
                    <div key={label} className="flex text-zinc-900" style={{
                        fontFamily: "'Inter Tight', sans-serif", fontSize: Math.round(1.4 * S),
                        fontWeight: 700, lineHeight: '1.5',
                    }}>
                        <span className="shrink-0" style={{ width: Math.round(22 * S) }}>{label}</span>
                        <span className="shrink-0 text-center" style={{ width: Math.round(2.5 * S) }}>:</span>
                        <span className="font-semibold" style={{ fontFamily: "'Manrope', sans-serif" }}>XXXXXXX</span>
                    </div>
                ))}
            </div>

            {/* Validity */}
            {config.show_validity && (
                <div className="absolute left-1/2 -translate-x-1/2 font-extrabold text-zinc-900" style={{
                    top: Math.round(28 * S), fontSize: Math.round(1.6 * S),
                    fontFamily: "'Inter Tight', sans-serif", letterSpacing: '0.02em',
                }}>
                    {String(config.validity_text || '')}
                </div>
            )}

            {/* Bottom row — photo + QR + signature */}
            <div className="absolute grid items-center" style={{
                left: Math.round(2.5 * S), right: Math.round(2 * S),
                top: Math.round(31 * S), bottom: Math.round(2 * S),
                gridTemplateColumns: 'auto auto 1fr', gap: Math.round(1.5 * S),
            }}>
                <div className="rounded bg-zinc-300/60" style={{ width: Math.round(16 * S), height: Math.round(21 * S) }} />
                {config.show_qr && (
                    <div className="rounded bg-white p-0.5 shadow-sm" style={{ width: Math.round(15 * S), height: Math.round(15 * S) }}>
                        <div className="grid size-full grid-cols-5 grid-rows-5 gap-px">
                            {Array.from({ length: 25 }).map((_, i) => (
                                <div key={i} className={i % 3 === 0 ? 'bg-zinc-800' : 'bg-white'} />
                            ))}
                        </div>
                    </div>
                )}
                <div className="flex h-full items-center justify-center rounded border border-dashed border-zinc-400/40 font-extrabold text-zinc-400" style={{
                    fontSize: Math.round(1.4 * S), fontFamily: "'Inter Tight', sans-serif",
                }}>
                    KEPALA SEKOLAH
                </div>
            </div>
        </div>
    );
}

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
                                {/* ATM Card Preview — 1mm ≈ 5.607px at 480px width */}
                                <CardPreview config={config} type={form.data.type} />
                            </div>
                            <p className="text-muted-foreground mt-3 text-center text-xs">85.6 × 54 mm · ATM Card Size · 400 DPI Export</p>
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
                                        {frames.filter((f) => f.category === form.data.type).map((f) => (
                                            <SelectItem key={f.id} value={String(f.id)}>{f.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle className="text-base">Header Band</CardTitle></CardHeader>
                            <CardContent className="space-y-3">
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="grid gap-1.5">
                                        <Label className="text-xs">Warna Awal</Label>
                                        <div className="flex gap-2">
                                            <input type="color" value={String(config.header_gradient_start)} onChange={(e) => updateConfig('header_gradient_start', e.target.value)} className="h-9 w-12 cursor-pointer rounded border" />
                                            <Input value={String(config.header_gradient_start)} onChange={(e) => updateConfig('header_gradient_start', e.target.value)} className="flex-1 text-xs" />
                                        </div>
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label className="text-xs">Warna Akhir</Label>
                                        <div className="flex gap-2">
                                            <input type="color" value={String(config.header_gradient_end)} onChange={(e) => updateConfig('header_gradient_end', e.target.value)} className="h-9 w-12 cursor-pointer rounded border" />
                                            <Input value={String(config.header_gradient_end)} onChange={(e) => updateConfig('header_gradient_end', e.target.value)} className="flex-1 text-xs" />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle className="text-base">Watermark & Teks</CardTitle></CardHeader>
                            <CardContent className="space-y-3">
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">Teks Watermark</Label>
                                    <Input value={String(config.watermark_text)} onChange={(e) => updateConfig('watermark_text', e.target.value)} placeholder="ORGANISASI SISWA INTRA SEKOLAH" />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">Teks Validitas</Label>
                                    <Input value={String(config.validity_text || '')} onChange={(e) => updateConfig('validity_text', e.target.value)} placeholder="BERLAKU S/D TAMAT BELAJAR" />
                                </div>
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Checkbox checked={config.show_emblem as boolean} onCheckedChange={(c) => updateConfig('show_emblem', c === true)} id="show_emblem" />
                                        <Label htmlFor="show_emblem" className="text-xs">Emblem OSIS (background)</Label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Checkbox checked={config.show_validity as boolean} onCheckedChange={(c) => updateConfig('show_validity', c === true)} id="show_validity" />
                                        <Label htmlFor="show_validity" className="text-xs">Teks Validitas</Label>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle className="text-base">Tipografi</CardTitle></CardHeader>
                            <CardContent className="space-y-3">
                                {[
                                    { key: 'font_school', label: 'Nama Sekolah' },
                                    { key: 'font_field', label: 'Field Body' },
                                ].map(({ key, label }) => (
                                    <div key={key} className="grid grid-cols-2 items-center gap-2">
                                        <Label className="text-xs">{label}</Label>
                                        <Input type="number" value={String(config[key])} onChange={(e) => updateConfig(key, Number(e.target.value))} min={8} max={28} />
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle className="text-base">Elemen</CardTitle></CardHeader>
                            <CardContent className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <Checkbox checked={config.show_qr as boolean} onCheckedChange={(c) => updateConfig('show_qr', c === true)} id="show_qr" />
                                    <Label htmlFor="show_qr" className="text-xs">QR Code</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox checked={config.show_signature as boolean} onCheckedChange={(c) => updateConfig('show_signature', c === true)} id="show_signature" />
                                    <Label htmlFor="show_signature" className="text-xs">Area Tanda Tangan</Label>
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
