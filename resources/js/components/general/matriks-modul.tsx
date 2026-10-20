import { Checkbox } from '@/components/ui/checkbox';

export type ModulGeneral = {
    permission: string;
    label: string;
    keterangan: string;
    khusus: boolean;
};

export type GrupModul = { grup: string; modul: ModulGeneral[] };

/**
 * Matriks centang modul General, dikelompokkan per menu. Modul yang sudah
 * datang dari peran ditampilkan tercentang dan terkunci; modul khusus super
 * admin terkunci bila pengguna tidak boleh memberikannya.
 */
export function MatriksModul({
    katalog,
    nilai,
    onUbah,
    dariPeran = [],
    bolehKhusus,
}: {
    katalog: GrupModul[];
    nilai: string[];
    onUbah: (nilai: string[]) => void;
    dariPeran?: string[];
    bolehKhusus: boolean;
}) {
    const terkunci = (m: ModulGeneral) =>
        dariPeran.includes(m.permission) || (m.khusus && !bolehKhusus);

    const ubah = (permission: string, pilih: boolean) =>
        onUbah(
            pilih
                ? [...new Set([...nilai, permission])]
                : nilai.filter((p) => p !== permission),
        );

    const ubahGrup = (grup: GrupModul) => {
        const bisa = grup.modul.filter((m) => !terkunci(m));
        const semua = bisa.every((m) => nilai.includes(m.permission));
        const kunci = bisa.map((m) => m.permission);

        onUbah(
            semua
                ? nilai.filter((p) => !kunci.includes(p))
                : [...new Set([...nilai, ...kunci])],
        );
    };

    return (
        <div className="grid gap-4">
            {katalog.map((grup) => (
                <div key={grup.grup} className="grid gap-2">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {grup.grup}
                        </span>
                        <button
                            type="button"
                            onClick={() => ubahGrup(grup)}
                            className="text-xs text-primary hover:underline"
                        >
                            Pilih / kosongkan grup
                        </button>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {grup.modul.map((m) => {
                            const id = `modul-${m.permission}`;
                            const dariRole = dariPeran.includes(m.permission);

                            return (
                                <label
                                    key={m.permission}
                                    htmlFor={id}
                                    className="flex cursor-pointer items-start gap-3 rounded-lg border p-3 hover:bg-muted/40 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-70"
                                >
                                    <Checkbox
                                        id={id}
                                        checked={
                                            dariRole ||
                                            nilai.includes(m.permission)
                                        }
                                        disabled={terkunci(m)}
                                        onCheckedChange={(c) =>
                                            ubah(m.permission, c === true)
                                        }
                                        className="mt-0.5"
                                    />
                                    <span className="grid gap-0.5">
                                        <span className="text-sm font-medium">
                                            {m.label}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {m.keterangan}
                                        </span>
                                        {dariRole && (
                                            <span className="text-xs text-primary">
                                                Dari peran
                                            </span>
                                        )}
                                        {m.khusus && !bolehKhusus && (
                                            <span className="text-xs text-amber-700 dark:text-amber-400">
                                                Hanya super admin yang bisa
                                                memberi modul ini.
                                            </span>
                                        )}
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}
