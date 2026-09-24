import { CheckCircle2, Circle, XCircle } from 'lucide-react';

export type Syarat = {
    kunci: string;
    label: string;
    nilai?: number;
    daftar?: string[];
};

/*
    Daftar syarat sandi yang mencentang dirinya sendiri saat diketik.

    Daftarnya TIDAK ditulis di sini. Ia datang dari App\Support\AturanSandi,
    kelas yang juga membangkitkan aturan validasinya — kalau halaman menyusun
    daftarnya sendiri, suatu hari ia akan menjanjikan "minimal 8 karakter" pada
    akun yang sebenarnya dituntut 12, lalu menolak tanpa memberi tahu apa yang
    kurang. Itu betul-betul terjadi.

    `larangan` untuk syarat yang hanya diketahui halamannya sendiri: di /daftar,
    sandi tidak boleh sama dengan nomor WhatsApp atau email yang baru saja
    diketik beberapa kolom di atasnya.

    Yang tidak bisa diperiksa di peramban — pemeriksaan kebocoran — muncul
    sebagai `catatan`, bukan sebagai centang yang berbohong.
*/
export default function SyaratSandi({
    password,
    syarat,
    catatan,
    larangan = [],
}: {
    password: string;
    syarat: Syarat[];
    catatan?: string | null;
    larangan?: (string | null | undefined)[];
}) {
    const terpakai = larangan
        .filter((v): v is string => !!v && v.trim() !== '')
        .map((v) => v.trim().toLowerCase());

    const daftar = [
        ...syarat.map((s) => ({ label: s.label, ok: penuhi(password, s) })),
        ...(terpakai.length > 0
            ? [
                  {
                      label: 'Bukan nomor HP atau email Anda',
                      ok:
                          password !== '' &&
                          !terpakai.includes(password.trim().toLowerCase()),
                  },
              ]
            : []),
    ];

    const semua = daftar.every((item) => item.ok);

    return (
        <div
            className={
                semua
                    ? 'grid gap-1.5 rounded-md border border-emerald-200 bg-emerald-50/60 px-3 py-2 dark:border-emerald-900 dark:bg-emerald-950/40'
                    : 'grid gap-1.5 rounded-md border border-border bg-muted/40 px-3 py-2'
            }
            aria-live="polite"
        >
            <ul className="grid gap-1 text-xs">
                {daftar.map((item) => (
                    <Butir key={item.label} ok={item.ok}>
                        {item.label}
                    </Butir>
                ))}
            </ul>
            {catatan && (
                <p className="text-xs text-muted-foreground">{catatan}</p>
            )}
        </div>
    );
}

/*
    Penanda di bawah kolom ulangi sandi. Merah hanya sesudah orang mulai
    mengetik di kolom itu — kolom kosong belum salah, baru belum diisi.
*/
export function CocokSandi({
    password,
    konfirmasi,
}: {
    password: string;
    konfirmasi: string;
}) {
    if (konfirmasi === '') {
        return null;
    }

    const cocok = konfirmasi === password;

    return (
        <p
            className={
                cocok
                    ? 'flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400'
                    : 'flex items-center gap-1.5 text-xs text-red-600 dark:text-red-400'
            }
            aria-live="polite"
        >
            {cocok ? (
                <CheckCircle2 className="size-4 shrink-0" aria-hidden />
            ) : (
                <XCircle className="size-4 shrink-0" aria-hidden />
            )}
            {cocok ? 'Kata sandi sama' : 'Kata sandi belum sama'}
        </p>
    );
}

function Butir({ ok, children }: { ok: boolean; children: React.ReactNode }) {
    return (
        <li
            className={
                ok
                    ? 'flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400'
                    : 'flex items-center gap-1.5 text-muted-foreground'
            }
        >
            {ok ? (
                <CheckCircle2 className="size-4 shrink-0" aria-hidden />
            ) : (
                <Circle className="size-4 shrink-0" aria-hidden />
            )}
            <span>{children}</span>
            <span className="sr-only">
                {ok ? '(terpenuhi)' : '(belum terpenuhi)'}
            </span>
        </li>
    );
}

function penuhi(password: string, syarat: Syarat): boolean {
    switch (syarat.kunci) {
        case 'panjang':
            return password.length >= (syarat.nilai ?? 8);
        case 'huruf':
            return /[a-z]/.test(password) && /[A-Z]/.test(password);
        case 'angka':
            return /[0-9]/.test(password);
        case 'simbol':
            return /[^A-Za-z0-9]/.test(password);
        case 'umum':
            // Kolom kosong belum memenuhi apa pun; centang hijau pada kolom
            // kosong membuat daftar ini tampak "sudah beres" sebelum diisi.
            return (
                password.trim() !== '' &&
                !(syarat.daftar ?? []).includes(password.trim().toLowerCase())
            );
        default:
            return false;
    }
}
