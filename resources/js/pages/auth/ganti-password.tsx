import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import SyaratSandi from '@/components/syarat-sandi';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/password/required';

/*
    `orangTua` datang dari server karena aturan kekuatan sandinya memang
    berbeda per peran (lihat ChangeRequiredPasswordController::aturan). Daftar
    centang hanya ditampilkan kepada yang aturannya dijanjikan halaman ini;
    peran lain memakai Password::defaults() yang di produksi lebih ketat, dan
    menampilkan daftar yang tidak sesuai aturannya sama saja berbohong.
*/
export default function GantiPassword({ orangTua }: { orangTua: boolean }) {
    const [password, setPassword] = useState('');

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
                            {orangTua && <SyaratSandi password={password} />}
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
