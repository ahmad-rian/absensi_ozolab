import { MoonStar, Sunrise } from 'lucide-react';
import InputError from '@/components/input-error';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';

type PrayerWindowCardProps = {
    idPrefix: string;
    title: string;
    description: string;
    tone: 'amber' | 'emerald';
    enabled: boolean;
    start: string;
    end: string;
    errors: { start?: string; end?: string };
    onEnabledChange: (value: boolean) => void;
    onStartChange: (value: string) => void;
    onEndChange: (value: string) => void;
};

const toneMap = {
    amber: { icon: Sunrise, className: 'size-5 text-amber-600' },
    emerald: { icon: MoonStar, className: 'size-5 text-emerald-600' },
} as const;

/**
 * Satu kartu per jenis sholat. Dipakai dua kali (Dhuha & Dzuhur) supaya blok
 * yang identik tidak digandakan — dan supaya jenis ketiga nanti gratis.
 */
export function PrayerWindowCard({
    idPrefix,
    title,
    description,
    tone,
    enabled,
    start,
    end,
    errors,
    onEnabledChange,
    onStartChange,
    onEndChange,
}: PrayerWindowCardProps) {
    const { icon: Icon, className } = toneMap[tone];

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-2">
                    <Icon className={className} />
                    <CardTitle>{title}</CardTitle>
                </div>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex items-center gap-3">
                    <Checkbox
                        id={`${idPrefix}_enabled`}
                        checked={enabled}
                        onCheckedChange={(checked) => onEnabledChange(Boolean(checked))}
                    />
                    <Label htmlFor={`${idPrefix}_enabled`} className="cursor-pointer text-sm font-medium">
                        Aktifkan {title.toLowerCase()}
                    </Label>
                </div>

                <Separator />

                <div className="grid gap-4 pl-7 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}_start`} className="text-sm font-medium">
                            Jam Mulai
                        </Label>
                        <Input
                            id={`${idPrefix}_start`}
                            type="time"
                            value={start}
                            onChange={(e) => onStartChange(e.target.value)}
                            disabled={!enabled}
                        />
                        <InputError message={errors.start} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}_end`} className="text-sm font-medium">
                            Jam Selesai
                        </Label>
                        <Input
                            id={`${idPrefix}_end`}
                            type="time"
                            value={end}
                            onChange={(e) => onEndChange(e.target.value)}
                            disabled={!enabled}
                        />
                        <InputError message={errors.end} />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
