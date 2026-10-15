import { Head } from '@inertiajs/react';
import { FormAturPoin } from '@/components/poin/form-atur-poin';
import type { SetupPoin } from '@/components/poin/form-atur-poin';
import { dashboard } from '@/routes';
import { index, pengaturan } from '@/routes/admin/poin';

/** Setup poin milik sekolah ini. Isinya sama dengan tab Poin di Pengaturan. */
export default function PoinPengaturan({ setup }: { setup: SetupPoin }) {
    return (
        <>
            <Head title="Atur Poin" />
            <div className="p-4 md:p-6">
                <FormAturPoin setup={setup} />
            </div>
        </>
    );
}

PoinPengaturan.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Poin Siswa', href: index() },
        { title: 'Atur Poin', href: pengaturan() },
    ],
};
