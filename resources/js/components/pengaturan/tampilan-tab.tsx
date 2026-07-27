import { router } from '@inertiajs/react';
import { ImageIcon, Upload } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';

/**
 * Satu-satunya tab tanpa tombol Simpan: unggahan berkas sudah punya endpoint
 * multipart sendiri dan tersimpan seketika. Menariknya ke dalam form biasa akan
 * memaksa `forceFormData` pada setiap penyimpanan pengaturan lain.
 */
export function TampilanTab({ logoUrl, faviconUrl }: { logoUrl: string; faviconUrl: string }) {
    const logoInputRef = useRef<HTMLInputElement>(null);
    const faviconInputRef = useRef<HTMLInputElement>(null);

    function upload(url: string, field: string, file?: File) {
        if (!file) {
            return;
        }

        router.post(url, { [field]: file }, { preserveScroll: true });
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-2">
                    <ImageIcon className="size-5 text-blue-600" />
                    <CardTitle>Logo & Favicon</CardTitle>
                </div>
                <CardDescription>Berkas tersimpan seketika setelah dipilih.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <BrandingSlot
                    label="Logo Aplikasi"
                    url={logoUrl}
                    buttonLabel="Upload Logo"
                    inputRef={logoInputRef}
                    onSelect={(file) => upload('/admin/pengaturan/upload-logo', 'logo', file)}
                />

                <Separator />

                <BrandingSlot
                    label="Favicon Aplikasi"
                    url={faviconUrl}
                    buttonLabel="Upload Favicon"
                    inputRef={faviconInputRef}
                    onSelect={(file) => upload('/admin/pengaturan/upload-favicon', 'favicon', file)}
                />
            </CardContent>
        </Card>
    );
}

function BrandingSlot({
    label,
    url,
    buttonLabel,
    inputRef,
    onSelect,
}: {
    label: string;
    url: string;
    buttonLabel: string;
    inputRef: React.RefObject<HTMLInputElement | null>;
    onSelect: (file?: File) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label className="text-sm font-medium">{label}</Label>
            <div className="flex items-center gap-4">
                {url ? (
                    <div className="flex size-20 items-center justify-center overflow-hidden rounded-lg border bg-white p-1">
                        <img src={url} alt={label} className="max-h-full max-w-full object-contain" />
                    </div>
                ) : (
                    <div className="text-muted-foreground bg-muted flex size-20 items-center justify-center rounded-lg border">
                        <ImageIcon className="size-8 opacity-40" />
                    </div>
                )}
                <div className="space-y-2">
                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={(e) => onSelect(e.target.files?.[0])}
                    />
                    <Button type="button" variant="outline" size="sm" onClick={() => inputRef.current?.click()}>
                        <Upload className="mr-2 size-4" />
                        {buttonLabel}
                    </Button>
                    <p className="text-muted-foreground text-xs">Format: JPG, PNG, WebP. Maks 2MB.</p>
                </div>
            </div>
        </div>
    );
}
