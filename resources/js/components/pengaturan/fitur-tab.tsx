import { useForm } from '@inertiajs/react';
import { Lock, Save, ToggleRight } from 'lucide-react';
import { type FormEvent, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { SchoolFeatureKey, SchoolFeatureMap } from '@/types';

export type FeatureCatalogEntry = {
    key: SchoolFeatureKey;
    label: string;
    description: string;
    group: string;
};

export type FeatureCatalog = {
    features: FeatureCatalogEntry[];
    always_on: string[];
};

export function FiturTab({
    features,
    catalog,
    onDirtyChange,
}: {
    features: SchoolFeatureMap;
    catalog: FeatureCatalog;
    onDirtyChange: (dirty: boolean) => void;
}) {
    const { data, setData, put, processing, isDirty } = useForm<{
        section: 'fitur';
        features: SchoolFeatureMap;
    }>({
        section: 'fitur',
        features,
    });

    useEffect(() => onDirtyChange(isDirty), [isDirty, onDirtyChange]);

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        put('/admin/pengaturan', { preserveScroll: true, preserveState: true });
    }

    function toggle(key: SchoolFeatureKey, value: boolean) {
        setData('features', { ...data.features, [key]: value });
    }

    const groups = catalog.features.reduce<Record<string, FeatureCatalogEntry[]>>((acc, entry) => {
        (acc[entry.group] ??= []).push(entry);
        return acc;
    }, {});

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
            {Object.entries(groups).map(([group, entries]) => (
                <Card key={group}>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <ToggleRight className="size-5 text-blue-600" />
                            <CardTitle>{group}</CardTitle>
                        </div>
                        <CardDescription>
                            Fitur yang dimatikan menghilangkan menunya dari sidebar dan menolak halamannya.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {entries.map((entry) => (
                            <div key={entry.key} className="flex items-start gap-3">
                                <Checkbox
                                    id={`feature_${entry.key}`}
                                    className="mt-0.5"
                                    checked={data.features[entry.key] === true}
                                    onCheckedChange={(checked) => toggle(entry.key, Boolean(checked))}
                                />
                                <div className="grid gap-0.5">
                                    <Label htmlFor={`feature_${entry.key}`} className="cursor-pointer text-sm font-medium">
                                        {entry.label}
                                    </Label>
                                    <p className="text-muted-foreground text-xs">{entry.description}</p>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            ))}

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Lock className="text-muted-foreground size-5" />
                        <CardTitle>Selalu Aktif</CardTitle>
                    </div>
                    <CardDescription>
                        Dashboard dan Pengaturan tidak bisa dimatikan — tanpa keduanya tidak ada jalan untuk
                        menyalakan fitur kembali. Modul sistem berlaku lintas sekolah, jadi saklarnya ada di
                        level Super Admin, bukan di sini.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-wrap gap-2">
                    {catalog.always_on.map((label) => (
                        <Badge key={label} variant="secondary">
                            {label}
                        </Badge>
                    ))}
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing || !isDirty}>
                    <Save className="mr-2 size-4" />
                    Simpan Fitur
                </Button>
            </div>
        </form>
    );
}
