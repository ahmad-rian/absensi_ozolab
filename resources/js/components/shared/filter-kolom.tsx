export type KolomFilter = { key: string; label: string; options: string[] };

/**
 * Satu pilihan per kolom data yang bisa difilter (mis. Kecamatan), seperti
 * filter kelas pada daftar siswa. Nilai dikirim sebagai `f[kunci]=nilai`.
 */
export function FilterKolom({
    kolom,
    nilai,
    onUbah,
}: {
    kolom: KolomFilter[];
    nilai: Record<string, string>;
    onUbah: (f: Record<string, string>) => void;
}) {
    return (
        <>
            {kolom
                .filter((k) => k.options.length > 0)
                .map((k) => (
                    <select
                        key={k.key}
                        aria-label={k.label}
                        className="h-9 max-w-56 rounded-md border bg-background px-2 text-sm"
                        value={nilai[k.key] ?? ''}
                        onChange={(e) => {
                            const berikut = { ...nilai };

                            if (e.target.value) {
                                berikut[k.key] = e.target.value;
                            } else {
                                delete berikut[k.key];
                            }

                            onUbah(berikut);
                        }}
                    >
                        <option value="">Semua {k.label.toLowerCase()}</option>
                        {k.options.map((o) => (
                            <option key={o} value={o}>
                                {o}
                            </option>
                        ))}
                    </select>
                ))}
        </>
    );
}
