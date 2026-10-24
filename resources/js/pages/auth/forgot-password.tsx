import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { langsung } from '@/routes/lupa-sandi';
import { email } from '@/routes/password';

/**
 * Lupa sandi. Orang tua langsung mengganti sandi di sini (email + nomor WA
 * terdaftar + hitungan), tanpa membuka email. Admin dan guru tetap lewat
 * link email — akun mereka membuka data seluruh siswa.
 */
export default function ForgotPassword({
    status,
    soal,
}: {
    status?: string;
    soal: string;
}) {
    return (
        <>
            <Head title="Lupa Kata Sandi" />

            {status && (
                <Alert className="border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}

            <div className="space-y-8">
                <Form
                    {...langsung.form()}
                    errorBag="langsung"
                    resetOnError={['captcha', 'password', 'password_confirmation']}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <p className="text-sm text-muted-foreground">
                                <span className="font-medium text-foreground">
                                    Orang tua / wali:
                                </span>{' '}
                                ganti sandi langsung di sini, tidak perlu buka
                                email.
                            </p>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email login</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="email"
                                    autoFocus
                                    required
                                    placeholder="contoh: budi@gmail.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="whatsapp">
                                    Nomor WhatsApp yang terdaftar di sekolah
                                </Label>
                                <Input
                                    id="whatsapp"
                                    type="tel"
                                    inputMode="tel"
                                    name="whatsapp"
                                    autoComplete="tel"
                                    required
                                    placeholder="contoh: 0812xxxxxxxx"
                                />
                                <InputError message={errors.whatsapp} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Sandi baru</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    autoComplete="new-password"
                                    required
                                    placeholder="minimal 8 karakter"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Ulangi sandi baru
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    required
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="captcha">
                                    Berapa hasil {soal}?
                                </Label>
                                <Input
                                    id="captcha"
                                    name="captcha"
                                    inputMode="numeric"
                                    pattern="[0-9]*"
                                    autoComplete="off"
                                    required
                                    className="max-w-32"
                                />
                                <InputError message={errors.captcha} />
                            </div>

                            <Button className="w-full" disabled={processing}>
                                {processing && <Spinner />}
                                Simpan sandi baru
                            </Button>
                        </>
                    )}
                </Form>

                <details className="rounded-lg border p-4 text-sm">
                    <summary className="cursor-pointer font-medium">
                        Admin / guru / akun lain: kirim link reset ke email
                    </summary>
                    <Form {...email.form()} className="mt-4 grid gap-3">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="email-link">Email</Label>
                                    <Input
                                        id="email-link"
                                        type="email"
                                        name="email"
                                        autoComplete="off"
                                        placeholder="email@sekolah.test"
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Kirim Link Reset Kata Sandi
                                </Button>
                            </>
                        )}
                    </Form>
                </details>

                <div className="text-center text-sm text-muted-foreground">
                    <span>Kembali ke </span>
                    <TextLink href={login()}>halaman masuk</TextLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Lupa Kata Sandi',
    description: 'Orang tua bisa langsung membuat sandi baru di halaman ini',
};
