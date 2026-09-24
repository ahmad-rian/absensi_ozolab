import { Check } from 'lucide-react';

export type Syarat = { kunci: string; label: string; nilai?: number };

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

    Yang tidak bisa diperiksa di peramban — daftar-tolak sandi umum dan
    pemeriksaan kebocoran — muncul sebagai `catatan`, bukan sebagai centang
    yang berbohong.
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
                      ok: !terpakai.includes(password.trim().toLowerCase()),
                  },
              ]
            : []),
    ];

    return (
        <div className="grid gap-1">
            <ul className="flex flex-wrap gap-x-3 gap-y-1 text-xs">
                {daftar.map((item) => (
                    <li
                        key={item.label}
                        className={
                            item.ok
                                ? 'flex items-center gap-1 text-emerald-600 dark:text-emerald-400'
                                : 'flex items-center gap-1 text-muted-foreground'
                        }
                    >
                        {item.ok ? (
                            <Check className="size-3.5" aria-hidden />
                        ) : (
                            <span
                                className="size-1.5 rounded-full bg-current"
                                aria-hidden
                            />
                        )}
                        {item.label}
                    </li>
                ))}
            </ul>
            {catatan && (
                <p className="text-xs text-muted-foreground">{catatan}</p>
            )}
        </div>
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
        default:
            return false;
    }
}
