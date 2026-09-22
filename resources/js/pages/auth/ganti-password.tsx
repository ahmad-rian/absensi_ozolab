import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/password/required';
export default function GantiPassword() {
    return (
        <>
            <Head title="Ganti kata sandi" />
            <Form {...update.form()}>
                {({ errors, processing }) => (
                    <div className="grid gap-6">
                        <p>Buat kata sandi pribadi sebelum membuka aplikasi.</p>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Kata sandi baru</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                required
                            />
                            <InputError message={errors.password} />
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
