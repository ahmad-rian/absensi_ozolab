import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';
import FieldBuilder, { type FormField } from './field-builder';

export default function KartuBebasDataCreate() {
    const [name, setName] = useState('');
    const [fields, setFields] = useState<FormField[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});
        router.post(
            '/kartu-bebas/data',
            { name, fields },
            {
                onError: (errs) => setErrors(errs as Record<string, string>),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <>
            <Head title="Buat Format Data" />
            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" onClick={() => router.get('/kartu-bebas/data')}>
                        <ArrowLeft className="size-5" />
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">Buat Format Data</h1>
                        <p className="text-muted-foreground text-sm">Tentukan nama format dan daftar field yang akan diisi.</p>
                    </div>
                </div>

                <Card>
                    <CardContent className="p-5">
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <FieldBuilder name={name} fields={fields} errors={errors} onName={setName} onFields={setFields} />
                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" onClick={() => router.get('/kartu-bebas/data')}>
                                    Batal
                                </Button>
                                <Button type="submit" disabled={processing} className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                                    {processing && <Loader2 className="size-4 animate-spin" />}
                                    Simpan Format
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

KartuBebasDataCreate.layout = (page: React.ReactNode) => (
    <KartuBebasLayout
        breadcrumbs={[
            { title: 'Dashboard', href: '/kartu-bebas' },
            { title: 'Data', href: '/kartu-bebas/data' },
            { title: 'Buat', href: '/kartu-bebas/data/create' },
        ]}
    >
        {page}
    </KartuBebasLayout>
);
