import { Form, Head } from '@inertiajs/react';
import { AlertCircle, Lock, Mail } from 'lucide-react';
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

/** Field lembut abu tanpa garis, seperti kartu referensi; ikon di kiri. */
const KELAS_FIELD =
    'h-11 rounded-[12px] border-transparent bg-zinc-100/90 pl-10 shadow-none focus-visible:bg-white dark:bg-white/5 dark:focus-visible:bg-white/10';

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
                            <div className="rounded-md border border-emerald-200 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">
                                {status}
                            </div>
                        )}

                        {errors.email && !errors.email.includes('required') && (
                            <Alert variant="destructive">
                                <AlertCircle className="size-4" />
                                <AlertDescription>
                                    {errors.email}
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="grid gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="email" className="sr-only">
                                    Email
                                </Label>
                                <div className="relative">
                                    <Mail className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-zinc-400" />
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                        placeholder="Email"
                                        className={KELAS_FIELD}
                                    />
                                </div>
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="password" className="sr-only">
                                    Kata sandi
                                </Label>
                                <div className="relative">
                                    <Lock className="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-zinc-400" />
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="Kata sandi"
                                        className={KELAS_FIELD}
                                    />
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center justify-between">
                                <label
                                    htmlFor="remember"
                                    className="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400"
                                >
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={3}
                                    />
                                    Ingat saya
                                </label>
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="text-xs font-medium text-zinc-800 no-underline hover:underline dark:text-zinc-200"
                                        tabIndex={5}
                                    >
                                        Lupa kata sandi?
                                    </TextLink>
                                )}
                            </div>

                            <Button
                                type="submit"
                                tabIndex={4}
                                disabled={processing}
                                className="mt-2 h-11 w-full rounded-[12px] bg-[linear-gradient(180deg,#3A3F47_0%,#15181D_100%)] text-sm font-semibold text-white shadow-[0_8px_20px_-8px_rgba(15,18,24,0.6),inset_0_1px_0_rgba(255,255,255,0.12)] hover:brightness-110 dark:bg-[linear-gradient(180deg,#FFFFFF_0%,#E4E8EE_100%)] dark:text-zinc-900"
                            >
                                {processing ? <Spinner /> : 'Masuk'}
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
                        <div className="flex items-center gap-3 text-[11px] text-zinc-400">
                            <span className="h-px flex-1 border-t border-dashed border-zinc-300 dark:border-zinc-700" />
                            Orang tua siswa
                            <span className="h-px flex-1 border-t border-dashed border-zinc-300 dark:border-zinc-700" />
                        </div>
                        <p className="-mt-2 text-center text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                            Masuk dengan email yang terdaftar di sekolah. Belum
                            punya akun? Hubungi wali kelas atau operator
                            sekolah.
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Masuk dengan email',
    description: 'Untuk sekolah, orang tua siswa, dan penyelenggara kegiatan.',
};
