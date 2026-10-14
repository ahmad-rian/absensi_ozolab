import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { accept } from '@/routes/undangan';

type Props = {
    token: string;
    valid: boolean;
    email: string | null;
    role: string | null;
    penyelenggara: string | null;
};

export default function UndanganShow({
    token,
    valid,
    email,
    role,
    penyelenggara,
}: Props) {
    return (
        <>
            <Head title="Terima undangan" />
            <div className="flex min-h-svh items-center justify-center bg-muted/40 p-4">
                <div className="w-full max-w-md rounded-xl border bg-background p-6 shadow-sm">
                    {valid ? (
                        <>
                            <h1 className="text-xl font-semibold">
                                Bergabung dengan {penyelenggara}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Anda diundang sebagai {role}. Buat kata sandi
                                untuk akun{' '}
                                <span className="font-medium">{email}</span>.
                            </p>
                            <Form
                                {...accept.form(token)}
                                resetOnSuccess={[
                                    'password',
                                    'password_confirmation',
                                ]}
                                className="mt-6 grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                Nama lengkap
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                autoComplete="name"
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="password">
                                                Kata sandi
                                            </Label>
                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                autoComplete="new-password"
                                                required
                                            />
                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="password_confirmation">
                                                Ulangi kata sandi
                                            </Label>
                                            <PasswordInput
                                                id="password_confirmation"
                                                name="password_confirmation"
                                                autoComplete="new-password"
                                                required
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            Buat akun & masuk
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </>
                    ) : (
                        <>
                            <h1 className="text-xl font-semibold">
                                Undangan tidak berlaku
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Tautan ini sudah dipakai, sudah dibatalkan, atau
                                lewat masa berlaku 7 hari. Minta admin
                                penyelenggara Anda mengirim undangan baru. Bila
                                Anda sudah punya akun, silakan masuk.
                            </p>
                            <Button asChild variant="outline" className="mt-6">
                                <Link href={login()}>Ke halaman masuk</Link>
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}
