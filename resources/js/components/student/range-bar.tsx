import { FileSpreadsheet, FileText, type LucideIcon } from 'lucide-react';
import { useId } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ExportLink = { label: string; href: string; icon: LucideIcon };

/**
 * Rakit tautan Excel+PDF untuk satu jenis laporan.
 *
 * Href SELALU dibangun dari rentang yang sudah dikonfirmasi server, bukan dari
 * state input lokal — kalau tidak, berkas yang diunduh berbeda dengan chart di
 * layar sampai tombol Terapkan ditekan.
 */
export function reportExports(studentId: string, kind: string, start: string, end: string, jenis?: string): ExportLink[] {
    const query = new URLSearchParams({ start_date: start, end_date: end });

    if (jenis) {
        query.set('jenis', jenis);
    }

    return [
        { label: 'Excel', icon: FileSpreadsheet, href: `/admin/siswa/${studentId}/laporan/${kind}/xlsx?${query}` },
        { label: 'PDF', icon: FileText, href: `/admin/siswa/${studentId}/laporan/${kind}/pdf?${query}` },
    ];
}

export function RangeBar({
    startDate,
    endDate,
    onStartChange,
    onEndChange,
    onApply,
    exports,
}: {
    startDate: string;
    endDate: string;
    onStartChange: (value: string) => void;
    onEndChange: (value: string) => void;
    onApply: () => void;
    exports: ExportLink[];
}) {
    // useId menjamin label tetap tertaut ke input yang benar walau dua RangeBar
    // pernah dirender bersamaan; versi lama memakai id literal yang duplikat.
    const uid = useId();

    return (
        <Card>
            <CardContent className="flex flex-col gap-4 p-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="grid flex-1 gap-3 sm:grid-cols-2 lg:max-w-md">
                    <div className="grid gap-1.5">
                        <Label htmlFor={`${uid}-start`} className="text-xs">
                            Dari Tanggal
                        </Label>
                        <Input
                            id={`${uid}-start`}
                            type="date"
                            value={startDate}
                            onChange={(e) => onStartChange(e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor={`${uid}-end`} className="text-xs">
                            Sampai Tanggal
                        </Label>
                        <Input
                            id={`${uid}-end`}
                            type="date"
                            value={endDate}
                            onChange={(e) => onEndChange(e.target.value)}
                        />
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button onClick={onApply}>Terapkan</Button>
                    {exports.map(({ label, href, icon: Icon }) => (
                        <Button key={label} variant="outline" asChild>
                            <a href={href}>
                                <Icon className="mr-1.5 size-4" />
                                {label}
                            </a>
                        </Button>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
