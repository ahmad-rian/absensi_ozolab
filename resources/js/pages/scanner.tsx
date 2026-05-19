import { Head, Link } from '@inertiajs/react';
import { QrCode } from 'lucide-react';
import { useState } from 'react';
import { QrScanner } from '@/components/scanner/qr-scanner';
import AppLogoIcon from '@/components/app-logo-icon';

type School = {
    id: number;
    name: string;
    slug: string;
};

interface PublicScannerProps {
    schools: School[];
}

export default function PublicScanner({ schools }: PublicScannerProps) {
    const [selectedSchoolId, setSelectedSchoolId] = useState<number | null>(
        schools.length === 1 ? schools[0].id : null,
    );

    const selectedSchool = schools.find((s) => s.id === selectedSchoolId);

    return (
        <>
            <Head title="Scanner Absensi" />
            <div className="bg-background min-h-dvh">
                <header className="border-b">
                    <div className="mx-auto flex h-14 max-w-xl items-center justify-between px-4">
                        <Link href="/" className="flex items-center gap-2">
                            <div className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600">
                                <AppLogoIcon className="size-4 fill-current text-white" />
                            </div>
                            <span className="text-sm font-bold">
                                {selectedSchool?.name ?? 'Absensi OZOLAB'}
                            </span>
                        </Link>
                    </div>
                </header>

                <main className="mx-auto max-w-xl px-4 py-6">
                    <div className="mb-6 text-center">
                        <div className="bg-primary/10 text-primary mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl">
                            <QrCode className="size-6" />
                        </div>
                        <h1 className="text-xl font-bold">Scanner Absensi</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Arahkan QR Code siswa ke kamera. Absensi otomatis tercatat.
                        </p>
                    </div>

                    {!selectedSchoolId ? (
                        <div className="space-y-3">
                            <p className="text-muted-foreground text-center text-sm font-medium">
                                Pilih sekolah terlebih dahulu:
                            </p>
                            <div className="grid gap-2">
                                {schools.map((school) => (
                                    <button
                                        key={school.id}
                                        type="button"
                                        onClick={() => setSelectedSchoolId(school.id)}
                                        className="border-border hover:bg-accent rounded-lg border px-4 py-3 text-left text-sm font-medium transition-colors"
                                    >
                                        {school.name}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <>
                            {schools.length > 1 && (
                                <div className="mb-4 flex items-center justify-between">
                                    <select
                                        value={selectedSchoolId}
                                        onChange={(e) => setSelectedSchoolId(Number(e.target.value))}
                                        className="border-border bg-background rounded-md border px-3 py-1.5 text-sm"
                                    >
                                        {schools.map((school) => (
                                            <option key={school.id} value={school.id}>
                                                {school.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            <QrScanner
                                scanEndpoint="/scan"
                                scanType="CHECK_IN"
                                extraPayload={{ school_id: selectedSchoolId }}
                            />
                        </>
                    )}
                </main>
            </div>
        </>
    );
}
