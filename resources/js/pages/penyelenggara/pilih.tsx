import { Head } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

export default function PilihPenyelenggara() {
    return (
        <>
            <Head title="Pilih penyelenggara" />
            <div className="p-4 md:p-6">
                <Card>
                    <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                        <Building2 className="size-8 text-muted-foreground" />
                        <h1 className="text-lg font-semibold">
                            Pilih penyelenggara dulu
                        </h1>
                        <p className="max-w-md text-sm text-muted-foreground">
                            Pilih penyelenggara di bagian atas menu samping.
                            Data yang tampil hanya milik penyelenggara yang
                            dipilih.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
