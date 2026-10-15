import { Input } from '@/components/ui/input';

/** Filter bersama halaman daftar lintas penyelenggara. */
export function FilterPenyelenggara({
    penyelenggaras,
    nilai,
    cari,
    placeholder,
    onUbah,
}: {
    penyelenggaras: { id: string; name: string }[];
    nilai: string;
    cari: string;
    placeholder: string;
    onUbah: (patch: { penyelenggara?: string; q?: string }) => void;
}) {
    return (
        <div className="flex flex-col gap-2 sm:flex-row">
            <select
                aria-label="Penyelenggara"
                className="h-9 rounded-md border bg-background px-2 text-sm sm:w-60"
                value={nilai}
                onChange={(e) => onUbah({ penyelenggara: e.target.value })}
            >
                <option value="">Semua penyelenggara</option>
                {penyelenggaras.map((p) => (
                    <option key={p.id} value={p.id}>
                        {p.name}
                    </option>
                ))}
            </select>
            <Input
                className="sm:max-w-xs"
                placeholder={placeholder}
                defaultValue={cari}
                onKeyDown={(e) =>
                    e.key === 'Enter' && onUbah({ q: e.currentTarget.value })
                }
            />
        </div>
    );
}
