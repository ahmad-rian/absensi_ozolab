import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import SyaratSandi, { CocokSandi } from '@/components/syarat-sandi';
import type { Syarat } from '@/components/syarat-sandi';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/password/required';

/*
    `syarat` dan `catatan` datang dari server (App\Support\AturanSandi) karena
    ambangnya berbeda per peran: orang tua delapan karakter tanpa aturan
    komposisi, peran lain dua belas plus simbol plus pemeriksaan kebocoran.
    Halaman ini tidak boleh menebaknya sendiri — sebelum props ini ada, ia
    memajang "minimal 8 karakter" kepada admin yang sebenarnya dituntut 12,
    dan sandi sembilan karakter ditolak tanpa alasan yang masuk akal.
*/
export default function GantiPassword({
    orangTua,
    sandi,
}: {
    orangTua: boolean;
    sandi: { syarat: Syarat[]; catatan: string | null };
}) {
    const [password, setPassword] = useState('');
    const [konfirmasi, setKonfirmasi] = useState('');

    return (
        <>
            <Head title="Ganti kata sandi" />
            <Form {...update.form()}>
                {({ errors, processing }) => (
                    <div className="grid gap-6">
                        <p>
                            {orangTua
                                ? 'Buat kata sandi untuk masuk ke Portal Orang Tua.'
                                : 'Buat kata sandi pribadi sebelum membuka aplikasi.'}
                        </p>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Kata sandi baru</Label>
                            {orangTua && (
                                <p className="rounded-md bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                    Kata sandi <strong>baru</strong> yang Anda
                                    tentukan sendiri untuk masuk ke portal ini —{' '}
                                    <strong>bukan sandi email Anda</strong>.
                                </p>
                            )}
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                required
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                            />
                            <InputError message={errors.password} />
                            <SyaratSandi
                                password={password}
                                syarat={sandi.syarat}
                                catatan={sandi.catatan}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Ulangi kata sandi baru
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                                value={konfirmasi}
                                onChange={(e) => setKonfirmasi(e.target.value)}
                            />
                            <CocokSandi
                                password={password}
                                konfirmasi={konfirmasi}
                            />
                        </div>
                        <Button disabled={processing}>Simpan kata sandi</Button>
                    </div>
                )}
            </Form>
        </>
    );
}

GantiPassword.layout = {
    title: 'Ganti kata sandi',
    description: 'Amankan akun Anda',
};
