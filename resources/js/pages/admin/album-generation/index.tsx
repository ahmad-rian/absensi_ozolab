import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, BookOpen, CheckSquare, Download, HardDrive, Loader2, Search, Square } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';

type Layout = {
    id: string;
    name: string;
    paper_size: string;
    orientation: string;
    columns: number;
    rows: number;
};

type Classroom = { id: string; name: string };

type StudentItem = {
    id: string;
    full_name: string;
    nis: string | null;
    classroom: string | null;
    classroom_id: string | null;
    has_photo: boolean;
};

type Props = {
    layouts: Layout[];
    classrooms: Classroom[];
    students: StudentItem[];
    driveConfigured: boolean;
};

export default function AlbumGenerationIndex({ layouts, classrooms, students, driveConfigured }: Props) {
    const [layoutId, setLayoutId] = useState('');
    const [classroomId, setClassroomId] = useState('');
    const [search, setSearch] = useState('');
    const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
    const [generating, setGenerating] = useState(false);

    // Filter students by classroom and search
    const filtered = useMemo(() => {
        let list = students;
        if (classroomId) {
            list = list.filter((s) => s.classroom_id === classroomId);
        }
        if (search.trim()) {
            const q = search.toLowerCase();
            list = list.filter((s) =>
                s.full_name.toLowerCase().includes(q) ||
                (s.nis && s.nis.toLowerCase().includes(q)),
            );
        }
        return list;
    }, [students, classroomId, search]);

    function toggleStudent(id: string) {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }

    function selectAll() {
        setSelectedIds(new Set(filtered.map((s) => s.id)));
    }

    function deselectAll() {
        setSelectedIds(new Set());
    }

    const allSelected = filtered.length > 0 && filtered.every((s) => selectedIds.has(s.id));

    function handleGenerate(e: FormEvent) {
        e.preventDefault();
        if (!layoutId || selectedIds.size === 0) return;

        setGenerating(true);
        const params = new URLSearchParams({ layout_id: layoutId });
        if (selectedIds.size < students.length) {
            params.set('student_ids', Array.from(selectedIds).join(','));
        } else if (classroomId) {
            params.set('classroom_id', classroomId);
        }

        window.location.href = `/admin/album-generation/download?${params.toString()}`;
        setTimeout(() => setGenerating(false), 5000);
    }

    // Auto-select all when classroom changes
    function handleClassroomChange(val: string) {
        setClassroomId(val === 'all' ? '' : val);
        setSearch('');
        // Auto-select all students in the new filter
        const newClassroomId = val === 'all' ? '' : val;
        const list = newClassroomId
            ? students.filter((s) => s.classroom_id === newClassroomId)
            : students;
        setSelectedIds(new Set(list.map((s) => s.id)));
    }

    return (
        <>
            <Head title="Generate Album" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Generate Album Foto</h1>
                    <p className="text-muted-foreground text-sm">Pilih siswa, layout, dan generate album siap cetak.</p>
                </div>

                {!driveConfigured && (
                    <Alert className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                        <AlertTriangle className="size-4 text-amber-600" />
                        <AlertDescription className="flex items-center justify-between">
                            <span className="text-amber-800 dark:text-amber-200">
                                Google Drive belum dikonfigurasi. Album disimpan lokal saja.
                            </span>
                            <Button variant="outline" size="sm" asChild className="ml-3 shrink-0">
                                <Link href="/admin/drive-config"><HardDrive className="mr-1.5 size-3.5" /> Setup Drive</Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                    {/* Student List */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Pilih Siswa</CardTitle>
                            <CardDescription>
                                {selectedIds.size} dari {filtered.length} siswa dipilih
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {/* Filters */}
                            <div className="flex gap-2">
                                <div className="relative flex-1">
                                    <Search className="text-muted-foreground absolute left-3 top-1/2 size-4 -translate-y-1/2" />
                                    <Input
                                        placeholder="Cari nama atau NIS..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-9"
                                    />
                                </div>
                                <Select value={classroomId || 'all'} onValueChange={handleClassroomChange}>
                                    <SelectTrigger className="w-40 shrink-0"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua Kelas</SelectItem>
                                        {classrooms.map((c) => (
                                            <SelectItem key={c.id} value={c.id}>{c.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Select All / Deselect */}
                            <div className="flex items-center gap-2">
                                <Button variant="ghost" size="sm" onClick={allSelected ? deselectAll : selectAll}>
                                    {allSelected ? <Square className="mr-1 size-3.5" /> : <CheckSquare className="mr-1 size-3.5" />}
                                    {allSelected ? 'Batal Pilih Semua' : 'Pilih Semua'}
                                </Button>
                                <span className="text-muted-foreground text-xs">({filtered.length} siswa)</span>
                            </div>

                            {/* Student Grid */}
                            <div className="max-h-[480px] overflow-y-auto rounded-lg border">
                                {filtered.length === 0 ? (
                                    <div className="text-muted-foreground py-12 text-center text-sm">
                                        Tidak ada siswa ditemukan.
                                    </div>
                                ) : (
                                    <div className="divide-y">
                                        {filtered.map((s) => (
                                            <label
                                                key={s.id}
                                                className={`flex cursor-pointer items-center gap-3 px-4 py-2.5 transition-colors hover:bg-muted/50 ${
                                                    selectedIds.has(s.id) ? 'bg-blue-50/50 dark:bg-blue-950/20' : ''
                                                }`}
                                            >
                                                <Checkbox
                                                    checked={selectedIds.has(s.id)}
                                                    onCheckedChange={() => toggleStudent(s.id)}
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium">{s.full_name}</p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {s.classroom ?? '-'} {s.nis && `· ${s.nis}`}
                                                    </p>
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Generate Panel */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <BookOpen className="size-4" /> Generate
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleGenerate} className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label>Layout Album</Label>
                                        <Select value={layoutId} onValueChange={setLayoutId}>
                                            <SelectTrigger><SelectValue placeholder="Pilih layout" /></SelectTrigger>
                                            <SelectContent>
                                                {layouts.map((l) => (
                                                    <SelectItem key={l.id} value={String(l.id)}>
                                                        {l.name} ({l.paper_size} {l.orientation === 'landscape' ? '↔' : '↕'} {l.columns}x{l.rows})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                                        <p className="font-medium">{selectedIds.size} siswa dipilih</p>
                                        {layoutId && (() => {
                                            const layout = layouts.find((l) => l.id === layoutId);
                                            if (!layout) return null;
                                            const perPage = layout.columns * layout.rows;
                                            const pages = Math.ceil(selectedIds.size / perPage);
                                            return (
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {perPage} siswa/halaman · {pages} halaman · {layout.orientation === 'landscape' ? 'Landscape' : 'Portrait'}
                                                </p>
                                            );
                                        })()}
                                    </div>

                                    <Button type="submit" disabled={!layoutId || selectedIds.size === 0 || generating} className="w-full gap-2">
                                        {generating ? <Loader2 className="size-4 animate-spin" /> : <Download className="size-4" />}
                                        Generate & Download
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="pt-6">
                                <ul className="text-muted-foreground list-inside list-disc space-y-1 text-xs">
                                    <li>Siswa tanpa foto ditampilkan dengan placeholder.</li>
                                    <li>Hasil berupa PNG (1 halaman) atau ZIP (multi halaman)</li>
                                    <li>Gunakan layout landscape untuk kertas horizontal</li>
                                </ul>
                            </CardContent>
                        </Card>
                    </div>
                </div>
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
