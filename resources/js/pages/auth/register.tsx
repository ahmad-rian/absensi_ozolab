import { Head, useForm } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { ArrowLeft, ArrowRight, Check, GraduationCap, Plus, Trash2, UserPlus, Users } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { login } from '@/routes';

type Classroom = { id: string; name: string; grade_level: number };
type Relation = { value: string; label: string };
type StudentForm = {
    full_name: string;
    gender: string;
    classroom_id: string;
    nis: string;
    birth_place: string;
    birth_date: string;
    address: string;
};

type Props = { passwordRules: string; classrooms: Classroom[]; relations: Relation[] };

const emptyStudent: StudentForm = { full_name: '', gender: '', classroom_id: '', nis: '', birth_place: '', birth_date: '', address: '' };

const slideVariants = {
    enter: (direction: number) => ({ x: direction > 0 ? 80 : -80, opacity: 0 }),
    center: { x: 0, opacity: 1 },
    exit: (direction: number) => ({ x: direction > 0 ? -80 : 80, opacity: 0 }),
};

export default function Register({ passwordRules, classrooms, relations }: Props) {
    const [step, setStep] = useState(1);
    const [direction, setDirection] = useState(1);
    const [showConfirm, setShowConfirm] = useState(false);

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        name: '',
        email: '',
        phone: '',
        relation: '',
        password: '',
        password_confirmation: '',
        students: [{ ...emptyStudent }] as StudentForm[],
    });

    function goToStep2() {
        clearErrors();
        const e: Record<string, string> = {};
        if (!data.name.trim()) { e.name = 'Nama lengkap wajib diisi.'; }
        if (!data.email.trim()) { e.email = 'Email wajib diisi.'; }
        if (!data.phone.trim()) { e.phone = 'Nomor WhatsApp wajib diisi.'; }
        if (!data.relation) { e.relation = 'Hubungan dengan siswa wajib dipilih.'; }
        if (!data.password) { e.password = 'Kata sandi wajib diisi.'; }
        if (data.password !== data.password_confirmation) { e.password_confirmation = 'Konfirmasi kata sandi tidak cocok.'; }
        if (Object.keys(e).length > 0) {
            Object.entries(e).forEach(([k, v]) => { errors[k] = v; });
            setData('name', data.name);
            return;
        }
        setDirection(1);
        setStep(2);
    }

    function goToStep1() {
        setDirection(-1);
        setStep(1);
    }

    function addStudent() { setData('students', [...data.students, { ...emptyStudent }]); }
    function removeStudent(i: number) { if (data.students.length > 1) { setData('students', data.students.filter((_, idx) => idx !== i)); } }
    function updateStudent(i: number, field: keyof StudentForm, value: string) {
        const u = [...data.students];
        u[i] = { ...u[i], [field]: value };
        setData('students', u);
    }

    function handleSubmit() {
        setShowConfirm(false);
        post('/register', { preserveScroll: true });
    }

    function onSubmitClick(e: React.FormEvent) {
        e.preventDefault();
        const hasError = data.students.some((s) => !s.full_name.trim() || !s.gender || !s.classroom_id);
        if (hasError) { handleSubmit(); return; }
        setShowConfirm(true);
    }

    return (
        <>
            <Head title="Daftar Akun" />

            {/* Stepper */}
            <div className="flex items-center gap-0">
                <StepPill number={1} label="Data Orang Tua" icon={<Users className="size-4" />} active={step === 1} completed={step > 1} onClick={goToStep1} />
                <div className="mx-1 flex-1">
                    <div className="bg-border relative h-0.5 rounded-full">
                        <motion.div
                            className="absolute inset-y-0 left-0 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600"
                            animate={{ width: step >= 2 ? '100%' : '0%' }}
                            transition={{ duration: 0.4, ease: 'easeInOut' }}
                        />
                    </div>
                </div>
                <StepPill number={2} label="Data Siswa" icon={<GraduationCap className="size-4" />} active={step === 2} completed={false} />
            </div>

            <form onSubmit={onSubmitClick}>
                <AnimatePresence mode="wait" custom={direction}>
                    {step === 1 && (
                        <motion.div
                            key="step1"
                            custom={direction}
                            variants={slideVariants}
                            initial="enter"
                            animate="center"
                            exit="exit"
                            transition={{ duration: 0.3, ease: 'easeInOut' }}
                            className="flex flex-col gap-5"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="name" className="text-sm font-medium">Nama Lengkap</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus placeholder="Nama lengkap orang tua/wali" className="h-11 rounded-xl" />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email" className="text-sm font-medium">Email</Label>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required placeholder="nama@contoh.com" className="h-11 rounded-xl" />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone" className="text-sm font-medium">Nomor WhatsApp</Label>
                                <div className="flex">
                                    <span className="border-input bg-muted/50 text-muted-foreground inline-flex items-center rounded-l-xl border border-r-0 px-3.5 text-sm font-medium">+62</span>
                                    <Input id="phone" type="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} required className="h-11 rounded-l-none rounded-r-xl" placeholder="812xxxxxxxx" />
                                </div>
                                <InputError message={errors.phone} />
                            </div>

                            <div className="grid gap-2">
                                <Label className="text-sm font-medium">Hubungan dengan Siswa</Label>
                                <Select value={data.relation} onValueChange={(val) => setData('relation', val)}>
                                    <SelectTrigger className="h-11 rounded-xl"><SelectValue placeholder="Pilih hubungan" /></SelectTrigger>
                                    <SelectContent>
                                        {relations.map((r) => (<SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.relation} />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="password" className="text-sm font-medium">Kata Sandi</Label>
                                    <PasswordInput id="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required autoComplete="new-password" placeholder="Min 8 karakter" passwordrules={passwordRules} className="h-11 rounded-xl" />
                                    <InputError message={errors.password} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation" className="text-sm font-medium">Konfirmasi</Label>
                                    <PasswordInput id="password_confirmation" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} required autoComplete="new-password" placeholder="Ulangi sandi" passwordrules={passwordRules} className="h-11 rounded-xl" />
                                    <InputError message={errors.password_confirmation} />
                                </div>
                            </div>

                            <Button type="button" className="mt-1 h-11 w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 font-semibold text-white shadow-lg shadow-blue-500/25" onClick={goToStep2}>
                                Lanjutkan
                                <ArrowRight className="ml-2 size-4" />
                            </Button>
                        </motion.div>
                    )}

                    {step === 2 && (
                        <motion.div
                            key="step2"
                            custom={direction}
                            variants={slideVariants}
                            initial="enter"
                            animate="center"
                            exit="exit"
                            transition={{ duration: 0.3, ease: 'easeInOut' }}
                            className="flex flex-col gap-4"
                        >
                            {data.students.map((student, index) => (
                                <Card key={index} className="overflow-hidden rounded-xl border-zinc-200/80 dark:border-zinc-800">
                                    <CardContent className="grid gap-4 p-5">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <div className="flex size-7 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600 text-xs font-bold text-white">
                                                    {index + 1}
                                                </div>
                                                <span className="text-sm font-semibold">Siswa {index + 1}</span>
                                            </div>
                                            {data.students.length > 1 && (
                                                <Button type="button" variant="ghost" size="sm" className="size-8 rounded-lg text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950" onClick={() => removeStudent(index)}>
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            )}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label className="text-sm font-medium">Nama Lengkap Siswa</Label>
                                            <Input value={student.full_name} onChange={(e) => updateStudent(index, 'full_name', e.target.value)} required placeholder="Nama lengkap siswa" className="h-10 rounded-xl" />
                                            <InputError message={errors[`students.${index}.full_name`]} />
                                        </div>

                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-2">
                                                <Label className="text-sm font-medium">Jenis Kelamin</Label>
                                                <RadioGroup value={student.gender} onValueChange={(v) => updateStudent(index, 'gender', v)} className="flex gap-3">
                                                    <label htmlFor={`g-l-${index}`} className={`flex flex-1 cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 text-sm transition ${student.gender === 'LAKI_LAKI' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'hover:bg-muted/50'}`}>
                                                        <RadioGroupItem value="LAKI_LAKI" id={`g-l-${index}`} />
                                                        L
                                                    </label>
                                                    <label htmlFor={`g-p-${index}`} className={`flex flex-1 cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 text-sm transition ${student.gender === 'PEREMPUAN' ? 'border-pink-500 bg-pink-50 dark:bg-pink-950' : 'hover:bg-muted/50'}`}>
                                                        <RadioGroupItem value="PEREMPUAN" id={`g-p-${index}`} />
                                                        P
                                                    </label>
                                                </RadioGroup>
                                                <InputError message={errors[`students.${index}.gender`]} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label className="text-sm font-medium">Kelas</Label>
                                                <Select value={student.classroom_id} onValueChange={(v) => updateStudent(index, 'classroom_id', v)}>
                                                    <SelectTrigger className="h-10 rounded-xl"><SelectValue placeholder="Pilih" /></SelectTrigger>
                                                    <SelectContent>{classrooms.map((c) => (<SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>))}</SelectContent>
                                                </Select>
                                                <InputError message={errors[`students.${index}.classroom_id`]} />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-2">
                                                <Label className="text-sm font-medium">NIS <span className="text-muted-foreground font-normal">(opsional)</span></Label>
                                                <Input value={student.nis} onChange={(e) => updateStudent(index, 'nis', e.target.value)} placeholder="Auto-generate" className="h-10 rounded-xl" />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label className="text-sm font-medium">Tanggal Lahir</Label>
                                                <Input type="date" value={student.birth_date} onChange={(e) => updateStudent(index, 'birth_date', e.target.value)} className="h-10 rounded-xl" />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label className="text-sm font-medium">Tempat Lahir</Label>
                                            <Input value={student.birth_place} onChange={(e) => updateStudent(index, 'birth_place', e.target.value)} placeholder="Kota kelahiran" className="h-10 rounded-xl" />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label className="text-sm font-medium">Alamat</Label>
                                            <Textarea value={student.address} onChange={(e) => updateStudent(index, 'address', e.target.value)} placeholder="Alamat lengkap" rows={2} className="rounded-xl" />
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}

                            <Button type="button" variant="outline" className="h-11 w-full rounded-xl border-dashed" onClick={addStudent}>
                                <Plus className="mr-2 size-4" />
                                Tambah Siswa Lain
                            </Button>

                            {errors.students && <InputError message={errors.students} />}

                            <div className="flex gap-3">
                                <Button type="button" variant="outline" className="h-11 flex-1 rounded-xl" onClick={goToStep1}>
                                    <ArrowLeft className="mr-2 size-4" />
                                    Kembali
                                </Button>
                                <Button type="submit" disabled={processing} className="h-11 flex-1 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 font-semibold text-white shadow-lg shadow-blue-500/25">
                                    {processing ? <Spinner /> : <><UserPlus className="mr-2 size-4" />Daftar</>}
                                </Button>
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>

                <Separator className="my-4" />
                <p className="text-muted-foreground text-center text-sm">
                    Sudah punya akun?{' '}
                    <TextLink href={login()} className="font-semibold text-blue-600 dark:text-blue-400">Masuk</TextLink>
                </p>
            </form>

            {/* Confirmation Dialog */}
            <AlertDialog open={showConfirm} onOpenChange={setShowConfirm}>
                <AlertDialogContent className="rounded-2xl">
                    <AlertDialogHeader>
                        <AlertDialogTitle>Konfirmasi Pendaftaran</AlertDialogTitle>
                        <AlertDialogDescription>Periksa kembali data Anda sebelum mendaftar:</AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="grid gap-3 text-sm">
                        <div className="rounded-xl border bg-zinc-50/80 p-4 dark:bg-zinc-900/50">
                            <p className="font-semibold">{data.name}</p>
                            <p className="text-muted-foreground">{data.email}</p>
                            <p className="text-muted-foreground">+62{data.phone}</p>
                        </div>
                        <div className="rounded-xl border bg-zinc-50/80 p-4 dark:bg-zinc-900/50">
                            <p className="text-muted-foreground mb-2 text-xs font-medium uppercase tracking-wider">{data.students.length} Siswa</p>
                            {data.students.map((s, i) => (
                                <div key={i} className="flex items-center gap-2 py-1">
                                    <div className="flex size-6 items-center justify-center rounded-md bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-900 dark:text-blue-300">{i + 1}</div>
                                    <span className="font-medium">{s.full_name || `Siswa ${i + 1}`}</span>
                                    {s.classroom_id && <Badge variant="secondary" className="text-xs">{classrooms.find((c) => String(c.id) === s.classroom_id)?.name}</Badge>}
                                </div>
                            ))}
                        </div>
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="rounded-xl">Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleSubmit} className="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white">
                            <Check className="mr-2 size-4" />
                            Konfirmasi & Daftar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

function StepPill({ number, label, icon, active, completed, onClick }: {
    number: number; label: string; icon: React.ReactNode; active: boolean; completed: boolean; onClick?: () => void;
}) {
    return (
        <button type="button" className="flex items-center gap-2" onClick={onClick} disabled={!onClick}>
            <span className={`flex size-9 items-center justify-center rounded-xl text-xs font-bold transition-all ${
                active ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/25'
                : completed ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                : 'bg-muted text-muted-foreground'
            }`}>
                {completed ? <Check className="size-4" /> : icon}
            </span>
            <span className={`hidden text-sm sm:inline ${active ? 'font-semibold' : 'text-muted-foreground'}`}>{label}</span>
        </button>
    );
}

Register.layout = {
    title: 'Buat Akun Baru',
    description: 'Daftarkan diri Anda sebagai orang tua/wali dan data siswa untuk memulai.',
};
