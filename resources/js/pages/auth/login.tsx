import { Form, Head } from '@inertiajs/react';
import { AlertCircle, LogIn } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Masuk" />

            <Form {...store.form()} resetOnSuccess={['password']} className="flex flex-col gap-5">
                {({ processing, errors }) => (
                    <>
                        {status && (
                            <div className="rounded-xl border border-emerald-200 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">
                                {status}
                            </div>
                        )}

                        {errors.email && !errors.email.includes('required') && (
                            <Alert variant="destructive" className="rounded-xl">
                                <AlertCircle className="size-4" />
                                <AlertDescription>{errors.email}</AlertDescription>
                            </Alert>
                        )}

                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="email" className="text-sm font-medium">
                                    Email
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="nama@sekolah.test"
                                    className="h-11 rounded-xl"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password" className="text-sm font-medium">
                                        Kata Sandi
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink href={request()} className="ml-auto text-xs" tabIndex={5}>
                                            Lupa kata sandi?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Masukkan kata sandi"
                                    className="h-11 rounded-xl"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-2.5">
                                <Checkbox id="remember" name="remember" tabIndex={3} />
                                <Label htmlFor="remember" className="text-sm font-normal">
                                    Ingat saya di perangkat ini
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                tabIndex={4}
                                disabled={processing}
                                className="h-11 w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition-all hover:shadow-xl hover:shadow-blue-500/30"
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <>
                                        <LogIn className="mr-2 size-4" />
                                        Masuk
                                    </>
                                )}
                            </Button>
                        </div>

                        <Separator className="my-1" />

                        <p className="text-muted-foreground text-center text-sm">
                            Belum punya akun?{' '}
                            <TextLink href="/daftar" tabIndex={6} className="font-semibold text-blue-600 dark:text-blue-400">
                                Daftar sekarang
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Selamat Datang Kembali',
    description: 'Masuk ke akun Anda untuk mengakses sistem absensi sekolah.',
};
