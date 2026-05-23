import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, BookOpen, Download, HardDrive, Loader2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';

type Layout = {
    id: number;
    name: string;
    paper_size: string;
    orientation: string;
    columns: number;
    rows: number;
};

type Classroom = { id: number; name: string };

type Props = {
    layouts: Layout[];
    classrooms: Classroom[];
    driveConfigured: boolean;
};

export default function AlbumGenerationIndex({ layouts, classrooms, driveConfigured }: Props) {
    const [layoutId, setLayoutId] = useState('');
    const [classroomId, setClassroomId] = useState('');
    const [generating, setGenerating] = useState(false);

    function handleGenerate(e: FormEvent) {
        e.preventDefault();
        if (!layoutId) return;

        setGenerating(true);
        const params = new URLSearchParams({ layout_id: layoutId });
        if (classroomId) params.set('classroom_id', classroomId);

        // Use window.location for file download (not Inertia)
        window.location.href = `/admin/album-generation/download?${params.toString()}`;
        setTimeout(() => setGenerating(false), 3000);
    }

    return (
        <>
            <Head title="Generate Album" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Generate Album Foto</h1>
                    <p className="text-muted-foreground text-sm">
                        Buat album foto siswa dalam format halaman siap cetak.
                    </p>
                </div>

                {!driveConfigured && (
                    <Alert className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                        <AlertTriangle className="size-4 text-amber-600" />
                        <AlertDescription className="flex items-center justify-between">
                            <span className="text-amber-800 dark:text-amber-200">
                                Google Drive belum dikonfigurasi. Album akan disimpan lokal saja.
                            </span>
                            <Button variant="outline" size="sm" asChild className="ml-3 shrink-0">
                                <Link href="/admin/drive-config">
                                    <HardDrive className="mr-1.5 size-3.5" /> Setup Drive
                                </Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <BookOpen className="size-4" /> Generate Album
                        </CardTitle>
                        <CardDescription>
                            Pilih layout dan kelas, lalu generate. Hasilnya berupa file gambar per halaman (atau ZIP jika lebih dari 1 halaman).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleGenerate} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Layout Album</Label>
                                    <Select value={layoutId} onValueChange={setLayoutId}>
                                        <SelectTrigger><SelectValue placeholder="Pilih layout" /></SelectTrigger>
                                        <SelectContent>
                                            {layouts.map((l) => (
                                                <SelectItem key={l.id} value={String(l.id)}>
                                                    {l.name} ({l.paper_size} {l.columns}x{l.rows})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Kelas (opsional)</Label>
                                    <Select value={classroomId || 'all'} onValueChange={(v) => setClassroomId(v === 'all' ? '' : v)}>
                                        <SelectTrigger><SelectValue placeholder="Semua kelas" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Semua Kelas</SelectItem>
                                            {classrooms.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <Button type="submit" disabled={!layoutId || generating} className="gap-2">
                                {generating ? <Loader2 className="size-4 animate-spin" /> : <Download className="size-4" />}
                                Generate & Download Album
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {/* Tips */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Tips</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="text-muted-foreground list-inside list-disc space-y-1 text-sm">
                            <li>Pastikan foto siswa sudah diupload untuk hasil terbaik.</li>
                            <li>Siswa tanpa foto akan ditampilkan dengan placeholder.</li>
                            <li>Jika lebih dari 1 halaman, hasilnya berupa file ZIP berisi semua halaman.</li>
                            <li>Gunakan layout portrait A4 3x4 untuk 12 siswa per halaman.</li>
                            <li>File yang digenerate tersimpan di server dan bisa diakses kembali.</li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AlbumGenerationIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Generate Album', href: '/admin/album-generation' },
    ],
};
