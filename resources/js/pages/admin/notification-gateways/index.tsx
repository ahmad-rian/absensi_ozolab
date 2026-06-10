import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, Loader2, MessageSquare, Save, Send, Trash2, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';

type ChannelKey = 'OZOLAB_WA' | 'FONNTE_WA' | 'TELEGRAM';

type Channels = {
    OZOLAB_WA: { is_active: boolean };
    FONNTE_WA: { is_active: boolean; display_phone: string; has_token: boolean };
    TELEGRAM: { is_active: boolean; has_token: boolean };
};

type Props = {
    schools: { id: string; name: string }[];
    selectedSchoolId: string | null;
    channels: Channels | null;
};

export default function NotificationGatewaysIndex({ schools, selectedSchoolId, channels }: Props) {
    const [test, setTest] = useState<{ channel: ChannelKey; destination: string } | null>(null);
    const [testResult, setTestResult] = useState<Record<string, { success: boolean; message: string }>>({});
    const [testing, setTesting] = useState<ChannelKey | null>(null);

    const form = useForm({
        channels: {
            OZOLAB_WA: { is_active: channels?.OZOLAB_WA.is_active ?? true },
            FONNTE_WA: { is_active: channels?.FONNTE_WA.is_active ?? false, fonnte_token: '', display_phone: channels?.FONNTE_WA.display_phone ?? '' },
            TELEGRAM: { is_active: channels?.TELEGRAM.is_active ?? false, bot_token: '' },
        },
    });

    function setChannel<K extends ChannelKey>(key: K, patch: Partial<(typeof form.data.channels)[K]>) {
        form.setData('channels', {
            ...form.data.channels,
            [key]: { ...form.data.channels[key], ...patch },
        });
    }

    function changeSchool(id: string) {
        router.get('/admin/notification-gateways', { school: id }, { preserveState: false });
    }

    function handleSubmit() {
        if (!selectedSchoolId) return;
        form.put(`/admin/notification-gateways/${selectedSchoolId}`, { preserveScroll: true });
    }

    function handleReset() {
        if (!selectedSchoolId) return;
        if (!confirm('Reset semua gateway sekolah ini ke default (Ozolab ID)?')) return;
        router.delete(`/admin/notification-gateways/${selectedSchoolId}`, { preserveScroll: true });
    }

    async function runTest(channel: ChannelKey, destination: string) {
        if (!selectedSchoolId || !destination.trim()) return;
        setTesting(channel);
        setTestResult((r) => ({ ...r, [channel]: undefined as never }));
        try {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const res = await fetch(`/admin/notification-gateways/${selectedSchoolId}/test`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ channel, destination }),
            });
            const data = await res.json();
            setTestResult((r) => ({ ...r, [channel]: data }));
        } catch {
            setTestResult((r) => ({ ...r, [channel]: { success: false, message: 'Gagal menghubungi server.' } }));
        }
        setTesting(null);
    }

    return (
        <>
            <Head title="Gateway Notifikasi" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Gateway Notifikasi</h1>
                        <p className="text-muted-foreground text-sm">
                            Atur channel notifikasi (WhatsApp & Telegram) untuk tiap sekolah. Hanya Super Admin.
                        </p>
                    </div>
                    <div className="grid gap-1.5">
                        <Label className="text-xs">Sekolah</Label>
                        <Select value={selectedSchoolId ?? ''} onValueChange={changeSchool}>
                            <SelectTrigger className="w-72">
                                <SelectValue placeholder="Pilih sekolah" />
                            </SelectTrigger>
                            <SelectContent>
                                {schools.map((s) => (
                                    <SelectItem key={s.id} value={s.id}>
                                        {s.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {!selectedSchoolId || !channels ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center text-sm">Belum ada sekolah. Tambahkan sekolah terlebih dahulu.</CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-6 lg:grid-cols-[1fr_380px]">
                        <div className="space-y-6">
                            {/* Ozolab WA */}
                            <ChannelCard
                                title="WhatsApp — Ozolab ID (Default)"
                                description="Gateway WhatsApp bawaan. Aktif default, tanpa kredensial khusus."
                                active={form.data.channels.OZOLAB_WA.is_active}
                                onToggle={(c) => setChannel('OZOLAB_WA', { is_active: c })}
                                toggleLabel="Aktifkan WhatsApp Ozolab ID"
                            >
                                <p className="text-muted-foreground text-sm">
                                    Notifikasi dikirim lewat nomor resmi Ozolab ID. Tidak perlu setup. Nonaktifkan jika sekolah pakai Fonnte sendiri.
                                </p>
                                <TestRow
                                    placeholder="Nomor tujuan (08xxx)"
                                    value={test?.channel === 'OZOLAB_WA' ? test.destination : ''}
                                    onChange={(v) => setTest({ channel: 'OZOLAB_WA', destination: v })}
                                    onTest={(v) => runTest('OZOLAB_WA', v)}
                                    busy={testing === 'OZOLAB_WA'}
                                    result={testResult.OZOLAB_WA}
                                />
                            </ChannelCard>

                            {/* Fonnte WA */}
                            <ChannelCard
                                title="WhatsApp — Fonnte (Nomor Sekolah)"
                                description="Nomor WhatsApp khusus sekolah via Fonnte.com."
                                active={form.data.channels.FONNTE_WA.is_active}
                                onToggle={(c) => setChannel('FONNTE_WA', { is_active: c })}
                                toggleLabel="Aktifkan WhatsApp Fonnte"
                            >
                                <div className="grid gap-2">
                                    <Label>Nomor WhatsApp</Label>
                                    <Input
                                        placeholder="08xxxxxxxxxx"
                                        value={form.data.channels.FONNTE_WA.display_phone}
                                        onChange={(e) => setChannel('FONNTE_WA', { display_phone: e.target.value })}
                                    />
                                    {form.errors['channels.FONNTE_WA.display_phone' as keyof typeof form.errors] && (
                                        <p className="text-destructive text-sm">{form.errors['channels.FONNTE_WA.display_phone' as keyof typeof form.errors]}</p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label>Token API Fonnte</Label>
                                    <Input
                                        type="password"
                                        placeholder={channels.FONNTE_WA.has_token ? '•••••••••••••• (biarkan kosong jika tidak diubah)' : 'Paste token dari dashboard Fonnte'}
                                        value={form.data.channels.FONNTE_WA.fonnte_token}
                                        onChange={(e) => setChannel('FONNTE_WA', { fonnte_token: e.target.value })}
                                    />
                                    {form.errors['channels.FONNTE_WA.fonnte_token' as keyof typeof form.errors] && (
                                        <p className="text-destructive text-sm">{form.errors['channels.FONNTE_WA.fonnte_token' as keyof typeof form.errors]}</p>
                                    )}
                                </div>
                                <TestRow
                                    placeholder="Nomor tujuan (08xxx)"
                                    value={test?.channel === 'FONNTE_WA' ? test.destination : ''}
                                    onChange={(v) => setTest({ channel: 'FONNTE_WA', destination: v })}
                                    onTest={(v) => runTest('FONNTE_WA', v)}
                                    busy={testing === 'FONNTE_WA'}
                                    result={testResult.FONNTE_WA}
                                />
                            </ChannelCard>

                            {/* Telegram */}
                            <ChannelCard
                                title="Telegram (Bot Sekolah)"
                                description="Push notifikasi via bot Telegram ke chat_id tiap orang tua."
                                active={form.data.channels.TELEGRAM.is_active}
                                onToggle={(c) => setChannel('TELEGRAM', { is_active: c })}
                                toggleLabel="Aktifkan Telegram"
                            >
                                <div className="grid gap-2">
                                    <Label>Bot Token</Label>
                                    <Input
                                        type="password"
                                        placeholder={channels.TELEGRAM.has_token ? '•••••••••••••• (biarkan kosong jika tidak diubah)' : 'Token dari @BotFather'}
                                        value={form.data.channels.TELEGRAM.bot_token}
                                        onChange={(e) => setChannel('TELEGRAM', { bot_token: e.target.value })}
                                    />
                                    {form.errors['channels.TELEGRAM.bot_token' as keyof typeof form.errors] && (
                                        <p className="text-destructive text-sm">{form.errors['channels.TELEGRAM.bot_token' as keyof typeof form.errors]}</p>
                                    )}
                                    <p className="text-muted-foreground text-xs">
                                        Orang tua harus <strong>/start</strong> ke bot dulu. Isi <code>telegram_chat_id</code> tiap ortu di menu Orang Tua.
                                    </p>
                                </div>
                                <TestRow
                                    placeholder="chat_id tujuan (mis. 123456789)"
                                    value={test?.channel === 'TELEGRAM' ? test.destination : ''}
                                    onChange={(v) => setTest({ channel: 'TELEGRAM', destination: v })}
                                    onTest={(v) => runTest('TELEGRAM', v)}
                                    busy={testing === 'TELEGRAM'}
                                    result={testResult.TELEGRAM}
                                />
                            </ChannelCard>

                            <div className="flex gap-2">
                                <Button onClick={handleSubmit} disabled={form.processing}>
                                    {form.processing ? <Loader2 className="mr-1.5 size-4 animate-spin" /> : <Save className="mr-1.5 size-4" />}
                                    Simpan Konfigurasi
                                </Button>
                                <Button type="button" variant="outline" className="text-red-600" onClick={handleReset}>
                                    <Trash2 className="mr-1.5 size-4" />
                                    Reset Default
                                </Button>
                            </div>
                        </div>

                        {/* Guide Panel */}
                        <div className="space-y-6">
                            <GuideCard title="Panduan: Fonnte (WhatsApp)" steps={fonnteSteps} link="https://fonnte.com" />
                            <GuideCard title="Panduan: Bot Telegram" steps={telegramSteps} link="https://t.me/BotFather" linkLabel="@BotFather" />
                            <Card className="h-fit">
                                <CardHeader>
                                    <CardTitle className="text-base">Cara Orang Tua Dapat chat_id</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    <ol className="space-y-2">
                                        <GuideStep n={1}>Orang tua buka bot sekolah di Telegram, tekan <strong>Start</strong>.</GuideStep>
                                        <GuideStep n={2}>
                                            Buka chat{' '}
                                            <a href="https://t.me/userinfobot" target="_blank" rel="noopener noreferrer" className="text-blue-600 underline">
                                                @userinfobot
                                            </a>
                                            , tekan Start — bot balas angka <strong>Id</strong>.
                                        </GuideStep>
                                        <GuideStep n={3}>Salin angka itu, isikan ke field <strong>Telegram Chat ID</strong> ortu di menu Orang Tua.</GuideStep>
                                    </ol>
                                    <div className="rounded-lg border bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                        Default semua sekolah pakai <strong>WhatsApp Ozolab ID</strong>. Fonnte & Telegram opsional.
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

function ChannelCard({
    title,
    description,
    active,
    onToggle,
    toggleLabel,
    children,
}: {
    title: string;
    description: string;
    active: boolean;
    onToggle: (c: boolean) => void;
    toggleLabel: string;
    children: React.ReactNode;
}) {
    return (
        <Card className={active ? 'border-green-200 dark:border-green-800' : ''}>
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <MessageSquare className="size-4" />
                            {title}
                        </CardTitle>
                        <CardDescription className="mt-1">{description}</CardDescription>
                    </div>
                    {active ? <CheckCircle2 className="size-6 shrink-0 text-green-600" /> : <XCircle className="text-muted-foreground size-6 shrink-0" />}
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex items-center gap-2">
                    <Checkbox id={toggleLabel} checked={active} onCheckedChange={(c) => onToggle(c === true)} />
                    <Label htmlFor={toggleLabel}>{toggleLabel}</Label>
                </div>
                {children}
            </CardContent>
        </Card>
    );
}

function TestRow({
    placeholder,
    value,
    onChange,
    onTest,
    busy,
    result,
}: {
    placeholder: string;
    value: string;
    onChange: (v: string) => void;
    onTest: (v: string) => void;
    busy: boolean;
    result?: { success: boolean; message: string };
}) {
    return (
        <div className="space-y-2 border-t pt-3">
            <div className="flex gap-2">
                <Input placeholder={placeholder} value={value} onChange={(e) => onChange(e.target.value)} className="flex-1" />
                <Button type="button" variant="secondary" onClick={() => onTest(value)} disabled={busy || !value.trim()}>
                    {busy ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
                    Test
                </Button>
            </div>
            {result && (
                <div className={`rounded-lg p-2.5 text-sm ${result.success ? 'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200' : 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200'}`}>
                    {result.message}
                </div>
            )}
        </div>
    );
}

function GuideCard({ title, steps, link, linkLabel }: { title: string; steps: string[]; link: string; linkLabel?: string }) {
    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
                <CardDescription>
                    <a href={link} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-blue-600 underline">
                        {linkLabel ?? link} <ExternalLink className="size-3" />
                    </a>
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ol className="space-y-2 text-sm">
                    {steps.map((s, i) => (
                        <GuideStep key={i} n={i + 1}>
                            <span dangerouslySetInnerHTML={{ __html: s }} />
                        </GuideStep>
                    ))}
                </ol>
            </CardContent>
        </Card>
    );
}

function GuideStep({ n, children }: { n: number; children: React.ReactNode }) {
    return (
        <li className="flex gap-2">
            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">{n}</span>
            <span>{children}</span>
        </li>
    );
}

const fonnteSteps = [
    'Daftar akun di fonnte.com.',
    'Klik <strong>Tambah Device</strong>, scan QR dengan WhatsApp nomor khusus sekolah (bukan pribadi).',
    'Setelah connected, salin <strong>Token API</strong> dari dashboard.',
    'Paste token + nomor di kartu Fonnte, lalu Simpan.',
    'Klik <strong>Test</strong> untuk verifikasi, lalu centang Aktifkan.',
];

const telegramSteps = [
    'Buka @BotFather di Telegram, kirim <strong>/newbot</strong>.',
    'Beri nama & username bot (harus diakhiri <code>bot</code>).',
    'BotFather balas <strong>Bot Token</strong> — salin.',
    'Paste token di kartu Telegram, Simpan, lalu centang Aktifkan.',
    'Minta tiap orang tua <strong>/start</strong> ke bot agar bisa menerima pesan.',
];

NotificationGatewaysIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Gateway Notifikasi', href: '/admin/notification-gateways' },
    ],
};
