import { router } from '@inertiajs/react';
import { MoonStar } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import type { PrayerStats } from '@/types/student-stats';

/**
 * Kepesertaan berlaku untuk SEMUA jenis sholat sekaligus — kolom
 * `students.prayer_opt_in` sengaja tidak dipecah per jenis, karena siswa
 * non-Muslim yang di-opt-in sekolah hampir pasti ikut keduanya.
 */
export function PrayerMembershipCard({ studentId, prayer }: { studentId: string; prayer: PrayerStats }) {
    const schoolRule = prayer.school_includes_all ? 'semua siswa ikut' : 'hanya siswa beragama Islam';
    const overridden = prayer.opt_in !== null;

    function setOptIn(value: boolean | null) {
        router.patch(
            `/admin/siswa/${studentId}/prayer-opt-in`,
            { prayer_opt_in: value },
            { preserveScroll: true, preserveState: true },
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <MoonStar className="size-5" />
                    Kepesertaan Sholat
                </CardTitle>
                <CardDescription>
                    Agama: {prayer.religion_label ?? '-'} · Aturan sekolah: {schoolRule} · Berlaku untuk semua jenis sholat
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                <label className="flex items-center gap-3 text-sm">
                    <Checkbox checked={prayer.covered} onCheckedChange={(checked) => setOptIn(Boolean(checked))} />
                    Ikutkan siswa ini di absen sholat
                </label>

                <div className="flex flex-wrap items-center gap-3">
                    <Badge variant={prayer.covered ? 'default' : 'secondary'}>
                        {prayer.covered ? 'Ikut absen sholat' : 'Tidak ikut'}
                    </Badge>
                    <span className="text-muted-foreground text-xs">
                        {overridden ? 'Diatur khusus untuk siswa ini' : 'Mengikuti aturan sekolah'}
                    </span>
                    {overridden && (
                        <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={() => setOptIn(null)}>
                            Kembalikan ke aturan sekolah
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
