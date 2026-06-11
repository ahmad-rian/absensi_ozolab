import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Copy, ExternalLink, Loader2, MessageSquare, QrCode, Save, Send, Trash2, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';

type ChannelKey = 'OZOLAB_WA' | 'FONNTE_WA' | 'TELEGRAM' | 'EMAIL';

type Channels = {
    OZOLAB_WA: { is_active: boolean };
    FONNTE_WA: { is_active: boolean; display_phone: string; has_token: boolean };
    TELEGRAM: {
        is_active: boolean;
        has_token: boolean;
        bot_username: string | null;
        deep_link: string | null;
        qr_svg: string | null;
        connected_count: number;
        total_parents: number;
    };
    EMAIL: {
        is_active: boolean;
        sender_email: string;
        sender_name: string;
        smtp_host: string;
        smtp_port: string | number;
        smtp_username: string;
        smtp_encryption: string;
        has_smtp_password: boolean;
        connected_count: number;
        total_parents: number;
    };
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
            EMAIL: {
                is_active: channels?.EMAIL.is_active ?? false,
                sender_email: channels?.EMAIL.sender_email ?? '',
                sender_name: channels?.EMAIL.sender_name ?? '',
                smtp_host: channels?.EMAIL.smtp_host ?? '',
                smtp_port: channels?.EMAIL.smtp_port ?? '587',
                smtp_username: channels?.EMAIL.smtp_username ?? '',
                smtp_password: '',
                smtp_encryption: channels?.EMAIL.smtp_encryption ?? 'tls',
            },
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
                                        Simpan token, lalu bagikan QR di bawah. Orang tua scan → tekan Start → bagikan nomor. chat_id tersimpan otomatis.
                                    </p>
                                </div>

                                {channels.TELEGRAM.is_active && channels.TELEGRAM.qr_svg && channels.TELEGRAM.deep_link && (
                                    <TelegramConnectQr
                                        qrSvg={channels.TELEGRAM.qr_svg}
                                        deepLink={channels.TELEGRAM.deep_link}
                                        username={channels.TELEGRAM.bot_username}
                                        connected={channels.TELEGRAM.connected_count}
                                        total={channels.TELEGRAM.total_parents}
                                    />
                                )}

                                <TestRow
                                    placeholder="chat_id tujuan (mis. 123456789)"
                                    value={test?.channel === 'TELEGRAM' ? test.destination : ''}
                                    onChange={(v) => setTest({ channel: 'TELEGRAM', destination: v })}
                                    onTest={(v) => runTest('TELEGRAM', v)}
                                    busy={testing === 'TELEGRAM'}
                                    result={testResult.TELEGRAM}
                                />
                            </ChannelCard>

                            {/* Email */}
                            <ChannelCard
                                title="Email (Pengirim Sekolah)"
                                description="Kirim notifikasi kehadiran via email ke alamat email orang tua. 1 sekolah 1 email pengirim."
                                active={form.data.channels.EMAIL.is_active}
                                onToggle={(c) => setChannel('EMAIL', { is_active: c })}
                                toggleLabel="Aktifkan Email"
                            >
                                <div className="grid gap-2">
                                    <Label>Email Pengirim</Label>
                                    <Input
                                        type="email"
                                        placeholder="absensi@sekolah.sch.id"
                                        value={form.data.channels.EMAIL.sender_email}
                                        onChange={(e) => setChannel('EMAIL', { sender_email: e.target.value })}
                                    />
                                    {form.errors['channels.EMAIL.sender_email' as keyof typeof form.errors] && (
                                        <p className="text-destructive text-sm">{form.errors['channels.EMAIL.sender_email' as keyof typeof form.errors]}</p>
                                    )}
                                    <p className="text-muted-foreground text-xs">
                                        Alamat pengirim (From) untuk semua email notifikasi sekolah ini. Kosongkan untuk memakai pengirim global default.
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label>Nama Pengirim</Label>
                                    <Input
                                        placeholder="Nama sekolah (opsional)"
                                        value={form.data.channels.EMAIL.sender_name}
                                        onChange={(e) => setChannel('EMAIL', { sender_name: e.target.value })}
                                    />
                                    {form.errors['channels.EMAIL.sender_name' as keyof typeof form.errors] && (
                                        <p className="text-destructive text-sm">{form.errors['channels.EMAIL.sender_name' as keyof typeof form.errors]}</p>
                                    )}
                                </div>

                                <div className="rounded-lg border bg-muted/30 p-3">
                                    <p className="mb-3 text-sm font-medium">Server SMTP Sekolah</p>
                                    <div className="grid gap-3">
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label>Host SMTP</Label>
                                                <Input
                                                    placeholder="smtp.gmail.com"
                                                    value={form.data.channels.EMAIL.smtp_host}
                                                    onChange={(e) => setChannel('EMAIL', { smtp_host: e.target.value })}
                                                />
                                                {form.errors['channels.EMAIL.smtp_host' as keyof typeof form.errors] && (
                                                    <p className="text-destructive text-sm">{form.errors['channels.EMAIL.smtp_host' as keyof typeof form.errors]}</p>
                                                )}
                                            </div>
                                            <div className="grid gap-2">
                                                <Label>Port</Label>
                                                <Input
                                                    type="number"
                                                    placeholder="587"
                                                    value={form.data.channels.EMAIL.smtp_port}
                                                    onChange={(e) => setChannel('EMAIL', { smtp_port: e.target.value })}
                                                />
                                                {form.errors['channels.EMAIL.smtp_port' as keyof typeof form.errors] && (
                                                    <p className="text-destructive text-sm">{form.errors['channels.EMAIL.smtp_port' as keyof typeof form.errors]}</p>
                                                )}
                                            </div>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Username SMTP</Label>
                                            <Input
                                                placeholder="akun@gmail.com"
                                                value={form.data.channels.EMAIL.smtp_username}
                                                onChange={(e) => setChannel('EMAIL', { smtp_username: e.target.value })}
                                            />
                                            {form.errors['channels.EMAIL.smtp_username' as keyof typeof form.errors] && (
                                                <p className="text-destructive text-sm">{form.errors['channels.EMAIL.smtp_username' as keyof typeof form.errors]}</p>
                                            )}
                                        </div>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label>Password SMTP</Label>
                                                <Input
                                                    type="password"
                                                    placeholder={channels.EMAIL.has_smtp_password ? '•••••••• (biarkan kosong jika tidak diubah)' : 'Password / App Password'}
                                                    value={form.data.channels.EMAIL.smtp_password}
                                                    onChange={(e) => setChannel('EMAIL', { smtp_password: e.target.value })}
                                                />
                                                {form.errors['channels.EMAIL.smtp_password' as keyof typeof form.errors] && (
                                                    <p className="text-destructive text-sm">{form.errors['channels.EMAIL.smtp_password' as keyof typeof form.errors]}</p>
                                                )}
                                            </div>
                                            <div className="grid gap-2">
                                                <Label>Enkripsi</Label>
                                                <Select value={form.data.channels.EMAIL.smtp_encryption} onValueChange={(v) => setChannel('EMAIL', { smtp_encryption: v })}>
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="tls">TLS (port 587)</SelectItem>
                                                        <SelectItem value="ssl">SSL (port 465)</SelectItem>
                                                        <SelectItem value="none">Tanpa enkripsi</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                    </div>
                                    <p className="text-muted-foreground mt-3 text-xs">
                                        Isi kredensial SMTP sekolah di sini — tidak perlu mengubah file server. Untuk Gmail, gunakan App Password (bukan password akun).
                                    </p>
                                </div>

                                <div className="rounded-lg border bg-muted/40 p-3 text-xs text-muted-foreground">
                                    {channels.EMAIL.connected_count} dari {channels.EMAIL.total_parents} orang tua punya email terdaftar.
                                </div>

                                <TestRow
                                    placeholder="email tujuan (mis. orangtua@email.com)"
                                    value={test?.channel === 'EMAIL' ? test.destination : ''}
                                    onChange={(v) => setTest({ channel: 'EMAIL', destination: v })}
                                    onTest={(v) => runTest('EMAIL', v)}
                                    busy={testing === 'EMAIL'}
                                    result={testResult.EMAIL}
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
                            <GuideCard title="Panduan: Email (Gmail)" steps={emailSteps} link="https://myaccount.google.com/apppasswords" linkLabel="Buat App Password Google" />
                            <Card className="h-fit">
                                <CardHeader>
                                    <CardTitle className="text-base">Isi Field Email (Gmail)</CardTitle>
                                    <CardDescription>Nilai yang dimasukkan ke kartu Email di atas.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <table className="w-full text-sm">
                                        <tbody>
                                            {emailFields.map((row) => (
                                                <tr key={row.field} className="border-b last:border-0">
                                                    <td className="text-muted-foreground py-2 pr-3 align-top whitespace-nowrap">{row.field}</td>
                                                    <td className="py-2 font-medium">
                                                        <code className="rounded bg-muted px-1.5 py-0.5 text-xs">{row.value}</code>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    <div className="mt-3 rounded-lg border bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                        Gmail gratis batas ~500 email/hari. Banyak siswa → pakai Google Workspace atau SMTP khusus (Brevo/Mailgun).
                                    </div>
                                </CardContent>
                            </Card>
                            <Card className="h-fit">
                                <CardHeader>
                                    <CardTitle className="text-base">Cara Orang Tua Hubungkan Telegram</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    <ol className="space-y-2">
                                        <GuideStep n={1}>Orang tua <strong>scan QR</strong> di kartu Telegram (cetak/sebar ke grup WA).</GuideStep>
                                        <GuideStep n={2}>Telegram terbuka di bot sekolah — tekan <strong>Start</strong>.</GuideStep>
                                        <GuideStep n={3}>Tekan <strong>Bagikan Nomor Saya</strong>. Sistem cocokkan dengan nomor WhatsApp terdaftar.</GuideStep>
                                        <GuideStep n={4}>Selesai — chat_id tersimpan otomatis. Status muncul di menu <strong>Orang Tua</strong>.</GuideStep>
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
    'Paste token di kartu Telegram, centang Aktifkan, lalu Simpan.',
    'Webhook & QR otomatis dibuat. Sebar QR ke orang tua.',
];

const emailSteps = [
    'Buka <strong>myaccount.google.com/security</strong> → aktifkan <strong>Verifikasi 2 Langkah</strong> (wajib lebih dulu).',
    'Buka <strong>myaccount.google.com/apppasswords</strong>, beri nama mis. <code>Absensi</code>, klik <strong>Create</strong>.',
    'Google tampilkan <strong>16 huruf</strong> (mis. <code>abcd efgh ijkl mnop</code>) — salin, spasi boleh dibuang.',
    'Isi field SMTP di kartu Email (lihat tabel di bawah), tempel 16 huruf itu di <strong>Password SMTP</strong>.',
    '<strong>Email Pengirim</strong> harus sama dengan alamat Gmail (username), jika beda email gagal/masuk spam.',
    'Centang <strong>Aktifkan Email</strong>, Simpan, lalu klik <strong>Test</strong> kirim ke email sendiri.',
];

const emailFields: { field: string; value: string }[] = [
    { field: 'Host SMTP', value: 'smtp.gmail.com' },
    { field: 'Port', value: '587' },
    { field: 'Enkripsi', value: 'TLS (atau SSL → port 465)' },
    { field: 'Username SMTP', value: 'emailkamu@gmail.com' },
    { field: 'Password SMTP', value: '16 huruf App Password' },
    { field: 'Email Pengirim', value: 'emailkamu@gmail.com (sama dgn username)' },
];

function TelegramConnectQr({
    qrSvg,
    deepLink,
    username,
    connected,
    total,
}: {
    qrSvg: string;
    deepLink: string;
    username: string | null;
    connected: number;
    total: number;
}) {
    const [copied, setCopied] = useState(false);

    function copyLink() {
        navigator.clipboard.writeText(deepLink).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    }

    return (
        <div className="space-y-3 rounded-xl border border-sky-200 bg-sky-50/60 p-4 dark:border-sky-900 dark:bg-sky-950/40">
            <div className="flex items-center gap-2 text-sm font-semibold text-sky-800 dark:text-sky-200">
                <QrCode className="size-4" /> QR Hubungkan Telegram Orang Tua
            </div>
            <div className="flex flex-col items-center gap-3 sm:flex-row sm:items-start">
                <div className="size-40 shrink-0 rounded-lg bg-white p-2 shadow-sm [&_svg]:size-full" dangerouslySetInnerHTML={{ __html: qrSvg }} />
                <div className="space-y-2 text-sm">
                    <p className="text-muted-foreground">
                        Orang tua scan QR ini → tekan Start → Bagikan Nomor. chat_id tersimpan otomatis.
                    </p>
                    {username && (
                        <p className="text-xs">
                            Bot: <code className="rounded bg-white px-1 py-0.5 dark:bg-zinc-800">@{username}</code>
                        </p>
                    )}
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" size="sm" variant="outline" onClick={copyLink}>
                            {copied ? <CheckCircle2 className="mr-1.5 size-3.5 text-green-600" /> : <Copy className="mr-1.5 size-3.5" />}
                            {copied ? 'Tersalin' : 'Salin Link'}
                        </Button>
                        <Button type="button" size="sm" variant="outline" asChild>
                            <a href={deepLink} target="_blank" rel="noopener noreferrer">
                                <ExternalLink className="mr-1.5 size-3.5" /> Buka Bot
                            </a>
                        </Button>
                    </div>
                    <p className="text-xs font-medium text-sky-700 dark:text-sky-300">
                        Terhubung: {connected} / {total} orang tua
                    </p>
                </div>
            </div>
        </div>
    );
}

NotificationGatewaysIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Gateway Notifikasi', href: '/admin/notification-gateways' },
    ],
};
