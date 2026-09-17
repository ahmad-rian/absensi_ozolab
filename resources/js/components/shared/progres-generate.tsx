import { AlertTriangle, CheckCircle2, Loader2 } from 'lucide-react';

export type Progres = {
    total: number;
    selesai: number;
    gagal: number;
    persen: number;
    status: 'processing' | 'completed' | 'failed';
};

/**
 * Bar kemajuan generate kartu.
 *
 * Angkanya SELALU datang dari server, tidak pernah dihitung ulang di sini dan
 * tidak pernah dianimasikan maju sendiri. Render kartu memanggil headless
 * Chrome lewat antrean: satu batch berisi ratusan siswa berjalan berkali-kali
 * lebih lama dari perkiraan mana pun yang bisa ditebak klien, dan bar yang
 * merangkak sendiri lalu berhenti di 90% adalah cara paling cepat membuat
 * operator menekan tombolnya untuk kedua kali.
 *
 * `gagal` tidak disembunyikan di balik warna. Batch yang separuh kartunya gagal
 * tetap mencapai 100%, dan tanpa angkanya ditulis terang-terangan itu terbaca
 * sebagai "selesai, semuanya beres".
 */
export function ProgresGenerate({ progres, label }: { progres: Progres; label?: string }) {
    const { total, selesai, gagal, persen, status } = progres;
    const beres = selesai + gagal;

    return (
        <div className="grid gap-2 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900">
            <div className="flex items-center justify-between gap-3 text-sm">
                <span className="flex items-center gap-2 font-medium">
                    {status === 'processing' && <Loader2 className="size-4 animate-spin text-blue-600" />}
                    {status === 'completed' && <CheckCircle2 className="size-4 text-green-600" />}
                    {status === 'failed' && <AlertTriangle className="size-4 text-amber-600" />}
                    {label ?? 'Membuat kartu'}
                </span>
                <span className="text-muted-foreground tabular-nums">
                    {beres}/{total} · {persen}%
                </span>
            </div>

            <div
                className="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700"
                role="progressbar"
                aria-valuenow={persen}
                aria-valuemin={0}
                aria-valuemax={100}
            >
                <div
                    className={`h-full rounded-full transition-[width] duration-500 ${
                        status === 'failed' ? 'bg-amber-500' : status === 'completed' ? 'bg-green-600' : 'bg-blue-600'
                    }`}
                    style={{ width: `${persen}%` }}
                />
            </div>

            <p className="text-muted-foreground text-xs">
                {status === 'processing' && 'Boleh ditinggal — kemajuannya tersimpan di server dan tetap benar kalau halaman ini dibuka lagi.'}
                {status === 'completed' && `${selesai} kartu selesai dibuat.`}
                {status === 'failed' && `${selesai} kartu selesai, ${gagal} gagal. Buka Riwayat Kartu untuk melihat sebabnya.`}
            </p>
        </div>
    );
}
