import { Check } from 'lucide-react';

/*
    Ketentuan kata sandi yang mencentang dirinya sendiri saat diketik.

    Dipakai di dua tempat yang memegang sandi orang tua: /daftar (sandinya
    dibuat) dan halaman ganti sandi wajib (sandinya diganti). Keduanya memakai
    aturan server yang sama, jadi daftar di sini harus ikut berubah kalau aturan
    itu diubah — lihat StudentRegistrationController::store() dan
    ChangeRequiredPasswordController::update().

    Ini MEMBERI TAHU, bukan menghalangi: di /daftar kolom yang sama juga dipakai
    orang tua lama untuk mencocokkan sandi lamanya, dan halaman tidak tahu kasus
    mana yang sedang berlangsung. Servernya yang tahu.
*/
export default function SyaratSandi({ password }: { password: string }) {
    const syarat = [
        { label: '8+ karakter', ok: password.length >= 8 },
        { label: 'huruf besar', ok: /[A-Z]/.test(password) },
        { label: 'huruf kecil', ok: /[a-z]/.test(password) },
        { label: 'angka', ok: /[0-9]/.test(password) },
    ];

    return (
        <ul className="flex flex-wrap gap-x-3 gap-y-1 text-xs">
            {syarat.map((s) => (
                <li
                    key={s.label}
                    className={
                        s.ok
                            ? 'flex items-center gap-1 text-emerald-600 dark:text-emerald-400'
                            : 'flex items-center gap-1 text-muted-foreground'
                    }
                >
                    {s.ok ? (
                        <Check className="size-3.5" aria-hidden />
                    ) : (
                        <span
                            className="size-1.5 rounded-full bg-current"
                            aria-hidden
                        />
                    )}
                    {s.label}
                </li>
            ))}
        </ul>
    );
}
