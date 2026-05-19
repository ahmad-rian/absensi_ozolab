import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Lupa Kata Sandi" />

            {status && (
                <Alert className="border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}

            <div className="space-y-6">
                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="off"
                                    autoFocus
                                    placeholder="email@sekolah.test"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="my-6">
                                <Button className="w-full" disabled={processing}>
                                    {processing && <Spinner />}
                                    Kirim Link Reset Kata Sandi
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="text-muted-foreground text-center text-sm">
                    <span>Kembali ke </span>
                    <TextLink href={login()}>halaman masuk</TextLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Lupa Kata Sandi',
    description: 'Masukkan email Anda untuk menerima link reset kata sandi',
};
