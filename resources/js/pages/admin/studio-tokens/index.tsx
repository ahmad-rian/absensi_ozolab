import { Head, router, useForm } from '@inertiajs/react';
import { Camera, Check, Copy, KeyRound, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useClipboard } from '@/hooks/use-clipboard';
import { dashboard } from '@/routes';

type Token = {
    id: string;
    name: string;
    school: string | null;
    school_id: string | null;
    created_by: string | null;
    created_at: string | null;
    last_used_at: string | null;
    revoked_at: string | null;
};

type Props = {
    tokens: Token[];
    schools: { id: string; name: string }[];
    studioUrl: string;
};

const SEMUA_SEKOLAH = 'semua';

export default function StudioTokensIndex({ tokens, schools, studioUrl }: Props) {
    const [showAdd, setShowAdd] = useState(false);
    const [tokenBaru, setTokenBaru] = useState<string | null>(null);
    const [tersalin, salin] = useClipboard();

    // `school_id` null berarti lintas sekolah. Select tidak bisa memakai nilai
    // kosong, jadi sentinel-nya hidup di komponen saja dan tidak pernah dikirim.
    const form = useForm<{ name: string; school_id: string | null }>({ name: '', school_id: null });

    /*
     * Token mentah hanya ada di respons ini dan tidak di mana pun lagi — tidak
     * di basis data, tidak di log. Kalau dialog ini ditutup sebelum disalin,
     * satu-satunya jalan adalah menerbitkan token baru.
     */
    useEffect(() => {
        return router.on('flash', (event) => {
            const nilai = (event as CustomEvent).detail?.flash?.studioTokenBaru;

            if (typeof nilai === 'string') {
                setShowAdd(false);
                form.reset();
                setTokenBaru(nilai);
            }
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/admin/studio-tokens', { preserveScroll: true });
    }

    function cabut(token: Token) {
        if (!confirm(`Cabut token "${token.name}"? Pemasangan yang memakainya langsung berhenti bekerja.`)) return;
        router.delete(`/admin/studio-tokens/${token.id}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Token Tyas Studio" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Token Tyas Studio</h1>
                        <p className="text-muted-foreground text-sm">
                            Kredensial untuk {studioUrl}. Satu token per pemasangan, supaya bisa dicabut satu per satu.
                        </p>
                    </div>
                    <Button className="gap-2" onClick={() => setShowAdd(true)}>
                        <Plus className="size-4" /> Terbitkan Token
                    </Button>
                </div>

                {tokens.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <Camera className="text-muted-foreground mb-4 size-12" />
                            <p className="text-muted-foreground text-sm">
                                Belum ada token. Terbitkan satu untuk memasang Tyas Studio.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {tokens.map((token) => (
                            <Card key={token.id} className={token.revoked_at ? 'opacity-50' : ''}>
                                <CardContent className="p-5">
                                    <div className="mb-3 flex items-start justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <KeyRound className="text-primary size-5" />
                                            <h3 className="font-semibold">{token.name}</h3>
                                        </div>
                                        {token.revoked_at ? (
                                            <Badge variant="secondary">Dicabut</Badge>
                                        ) : (
                                            <Badge variant="outline">Aktif</Badge>
                                        )}
                                    </div>

                                    <dl className="text-muted-foreground space-y-1 text-sm">
                                        <div className="flex justify-between gap-2">
                                            <dt>Sekolah</dt>
                                            <dd className="text-foreground text-right">{token.school ?? 'Semua sekolah'}</dd>
                                        </div>
                                        <div className="flex justify-between gap-2">
                                            <dt>Terakhir dipakai</dt>
                                            <dd className="text-foreground text-right">{token.last_used_at ?? 'Belum pernah'}</dd>
                                        </div>
                                        <div className="flex justify-between gap-2">
                                            <dt>Diterbitkan</dt>
                                            <dd className="text-foreground text-right">{token.created_by ?? '—'}</dd>
                                        </div>
                                    </dl>

                                    {!token.revoked_at && (
                                        <Button variant="ghost" size="sm" className="mt-4 w-full" onClick={() => cabut(token)}>
                                            <Trash2 className="mr-1 size-4 text-red-500" /> Cabut
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={showAdd} onOpenChange={setShowAdd}>
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>Terbitkan Token Studio</DialogTitle>
                            <DialogDescription>
                                Tokennya hanya ditampilkan sekali. Salin dan simpan di berkas .env pemasangan Studio.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama pemasangan</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="Laptop studio 1"
                                    autoFocus
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="school">Sekolah</Label>
                                <Select
                                    value={form.data.school_id ?? SEMUA_SEKOLAH}
                                    onValueChange={(v) => form.setData('school_id', v === SEMUA_SEKOLAH ? null : v)}
                                >
                                    <SelectTrigger id="school">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={SEMUA_SEKOLAH}>Semua sekolah</SelectItem>
                                        {schools.map((s) => (
                                            <SelectItem key={s.id} value={s.id}>
                                                {s.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">
                                    Pilih satu sekolah kalau Studio itu hanya melayani sekolah tersebut. Token yang terkunci
                                    tidak bisa melihat siswa sekolah lain sama sekali.
                                </p>
                                <InputError message={form.errors.school_id} />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowAdd(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Terbitkan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={tokenBaru !== null} onOpenChange={(open) => !open && setTokenBaru(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Salin sekarang</DialogTitle>
                        <DialogDescription>
                            Ini satu-satunya kali tokennya terlihat. Setelah dialog ini ditutup, tidak ada cara melihatnya
                            lagi — yang tersimpan di server hanya sidik jarinya.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex items-center gap-2 py-2">
                        <code className="bg-muted flex-1 overflow-x-auto rounded-md p-3 font-mono text-sm">{tokenBaru}</code>
                        <Button type="button" variant="outline" size="icon" onClick={() => tokenBaru && salin(tokenBaru)}>
                            {tersalin === tokenBaru ? <Check className="size-4" /> : <Copy className="size-4" />}
                        </Button>
                    </div>

                    <p className="text-muted-foreground text-xs">
                        Pasang di <code className="font-mono">.env</code> Tyas Studio sebagai{' '}
                        <code className="font-mono">PARENT_TOKEN</code>.
                    </p>

                    <DialogFooter>
                        <Button onClick={() => setTokenBaru(null)}>Sudah disalin</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

StudioTokensIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Token Tyas Studio', href: '/admin/studio-tokens' },
    ],
};
