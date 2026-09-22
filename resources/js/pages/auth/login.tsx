import { Form, Head } from '@inertiajs/react';
import { AlertCircle, LogIn, Users } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
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
                                <AlertDescription>
                                    {errors.email}
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="email"
                                    className="text-sm font-medium"
                                >
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
                                    placeholder="nama@email.com"
                                    className="h-11 rounded-xl"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label
                                        htmlFor="password"
                                        className="text-sm font-medium"
                                    >
                                        Kata Sandi
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-xs"
                                            tabIndex={5}
                                        >
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
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label
                                    htmlFor="remember"
                                    className="text-sm font-normal"
                                >
                                    Ingat saya
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                tabIndex={4}
                                disabled={processing}
                                className="h-11 w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition-all hover:shadow-xl hover:shadow-blue-500/30 disabled:opacity-50"
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

                        {/*
                            Catatan untuk orang tua, bukan untuk staf.
                            Diletakkan di bawah form supaya tidak memperlambat
                            yang sudah tahu akunnya, tapi tetap terbaca oleh
                            yang datang ke sini tanpa yakin ini untuk mereka.
                            Sebagian besar akun orang tua belum punya email
                            login, jadi kalimat "hubungi sekolah" di sini
                            adalah jalan keluar yang sebenarnya.
                        */}
                        <div className="mt-1 flex items-start gap-2.5 rounded-xl border border-zinc-200/80 bg-zinc-50/60 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                            <Users className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <p className="text-xs leading-relaxed text-muted-foreground">
                                <span className="font-medium text-foreground">
                                    Orang tua siswa
                                </span>{' '}
                                memakai email yang terdaftar di sekolah. Belum
                                menerima akun? Hubungi wali kelas atau operator
                                sekolah.
                            </p>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Selamat datang',
    description: 'Satu pintu masuk untuk sekolah dan orang tua siswa.',
};
