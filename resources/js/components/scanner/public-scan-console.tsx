import { Html5Qrcode } from 'html5-qrcode';
import {
    BookOpen,
    Camera,
    CheckCircle2,
    Clock,
    Loader2,
    LogIn,
    LogOut,
    Maximize,
    Minimize,
    MoonStar,
    RefreshCw,
    School as SchoolIcon,
    User,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { playErrorSound, playSuccessSound } from '@/components/scanner/use-scan-sound';

export type ScanSchool = { name: string; logo_url: string | null; is_active: boolean };

export type ScanStudentResult = {
    full_name: string;
    nis: string | null;
    no_absen: string | null;
    classroom: string | null;
    photo_url: string | null;
    status: string;
    /** CHECK_IN | CHECK_OUT | PRAYER */
    type: string;
    type_label: string;
    time: string;
};

type ScanResult = {
    success: boolean;
    message: string;
    student: ScanStudentResult | null;
};

type ScanLogItem = {
    id: number;
    student: ScanStudentResult | null;
    success: boolean;
    message: string;
    time: string;
};

type CameraDevice = { id: string; label: string };

type Props = {
    school: ScanSchool;
    /** Endpoint POST tujuan scan, mis. `/scan/{token}` atau `/scan/{token}/sholat`. */
    scanUrl: string;
    /** Baris kecil di bawah nama sekolah. */
    tagline: string;
    /** Kalimat panduan di bawah frame kamera. */
    hint?: string;
    /** Ditampilkan menggantikan konsol saat fitur belum aktif. */
    disabledNotice?: string | null;
};

let logId = 0;

const DATE_FMT: Intl.DateTimeFormatOptions = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };

/**
 * Berapa lama kartu yang SAMA diabaikan setelah berhasil tercatat.
 *
 * Tidak ada jeda menyeluruh: kartu berbeda boleh menyusul seketika, supaya
 * antrean di gerbang tidak tersendat. Penjaga ini hanya menahan pantulan —
 * pembaca RFID/barcode kerap memancarkan UID dua kali dalam satu tempelan,
 * dan tanpa penahan itu anak melihat kotak merah "Sudah absen masuk hari ini"
 * sedetik setelah dia benar-benar berhasil.
 */
const SAME_CARD_MS = 1500;

/** Umur kartu hasil di layar. Scan baru tetap menggantikannya seketika. */
const RESULT_MS = 2500;

/**
 * Batas diam antar-tombol sebelum buffer barcode gun dibuang.
 *
 * Timernya direset tiap tombol, jadi angka ini adalah jeda ANTAR-KARAKTER,
 * bukan total. Nilai lama 150 ms terlalu ketat: saat React sedang render di
 * perangkat lemot, satu keystroke bisa tertunda melewatinya dan buffer dibuang
 * di tengah UID — sisanya terkirim sebagai token potong dan ditolak server.
 * Manusia butuh ~1 detik untuk berganti kartu, jadi angka ini tidak akan
 * menyatukan dua tempelan.
 */
const KEY_IDLE_MS = 600;

/** UID kartu terpanjang yang masuk akal; sisanya pasti sampah. */
const MAX_BUFFER = 64;

/**
 * Panjang minimal untuk dikirim otomatis tanpa Enter.
 *
 * Token QR dan UID kartu selalu jauh lebih panjang dari ini, jadi ambang
 * segini tidak akan mengirim potongan ketikan yang belum selesai.
 */
const MIN_TOKEN = 6;

/**
 * Batas rata-rata jeda antar-tombol yang masih dianggap "diketik mesin".
 *
 * Pembaca RFID/barcode mengetik 5-20 ms per karakter; manusia tercepat pun
 * jauh di atas 50 ms. Ini yang memisahkan tempelan kartu dari orang yang
 * sedang mengetik di kotak manual — supaya ketikan tangan tidak terkirim
 * sendiri di tengah jalan.
 */
const MACHINE_MS_PER_KEY = 50;

/**
 * Token CSRF baru, diambil dengan memuat ulang halaman ini di latar.
 *
 * GET-nya sekaligus memperpanjang sesi, jadi percobaan kedua sesudah ini
 * punya token yang sah DAN sesi yang hidup. Meta tag di DOM ikut disegarkan
 * supaya kode lain yang membacanya tidak tertinggal.
 */
async function ambilTokenCsrf(): Promise<string | null> {
    try {
        const res = await fetch(window.location.href, {
            headers: { Accept: 'text/html' },
            cache: 'no-store',
        });
        const html = await res.text();
        const cocok = /name="csrf-token"\s+content="([^"]+)"/i.exec(html);
        const baru = cocok?.[1] ?? null;

        if (baru) {
            const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');

            if (meta) {
                meta.content = baru;
            }
        }

        return baru;
    } catch {
        return null;
    }
}

/** Elemen layar-penuh saat ini, termasuk varian WebKit di iPad. */
function layarPenuhSekarang(): Element | null {
    const dok = document as Document & { webkitFullscreenElement?: Element | null };

    return dok.fullscreenElement ?? dok.webkitFullscreenElement ?? null;
}

/**
 * Peramban yang dibuka DI DALAM aplikasi lain, bukan peramban sungguhan.
 *
 * WhatsApp, Instagram, Facebook, dan Line membuka tautan di WebView sendiri.
 * Di iOS, WebView itu MEMBLOKIR `getUserMedia` sepenuhnya — bukan menanyakan
 * izin, langsung menolak. Tautan gerbang ini dibagikan lewat WhatsApp, jadi
 * itu justru jalan masuk yang paling mungkin dipakai orang.
 *
 * Deteksi UA memang rapuh dan akan meleset suatu saat. Tapi yang dihasilkan
 * cuma kalimat bantuan yang lebih tepat; kameranya tetap dicoba lebih dulu,
 * dan pesan ini hanya muncul sesudah percobaan itu gagal.
 */
function peramban_dalam_aplikasi(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    return /FBAN|FBAV|Instagram|Line\/|WhatsApp|; wv\)/i.test(navigator.userAgent);
}

/**
 * Konsol scan layar-penuh: kamera QR, barcode gun, kartu hasil, riwayat.
 *
 * Dipakai bersama oleh absensi sekolah dan absen sholat — dua halaman itu
 * hanya berbeda endpoint dan label, jadi implementasinya tidak digandakan.
 */
export function PublicScanConsole({ school, scanUrl, tagline, hint, disabledNotice = null }: Props) {
    const [cameraStatus, setCameraStatus] = useState<'loading' | 'scanning' | 'error'>('loading');
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [cameras, setCameras] = useState<CameraDevice[]>([]);
    const [selectedCamera, setSelectedCamera] = useState<string>('');
    const [lastResult, setLastResult] = useState<ScanResult | null>(null);
    const [scanLog, setScanLog] = useState<ScanLogItem[]>([]);
    /**
     * Bentuk bacaan terakhir, ditampilkan hanya saat gagal.
     *
     * Operator memegang kartunya. Kalau layar bilang "terbaca 18 karakter"
     * padahal tokennya 35, bacaan yang terpotong kelihatan saat itu juga —
     * tanpa perlu membuka server. Ini yang selama ini hilang setiap kali ada
     * laporan "kartu tidak dikenali padahal datanya ada".
     */
    const [lastProbe, setLastProbe] = useState<{ len: number; awal: string; akhir: string } | null>(null);
    const [clock, setClock] = useState('');
    const [today, setToday] = useState('');
    const [isFullscreen, setIsFullscreen] = useState(false);

    const barcodeInputRef = useRef<HTMLInputElement>(null);
    const scannerRef = useRef<Html5Qrcode | null>(null);
    const mountedRef = useRef(true);
    /** Token yang requestnya sedang terbang — mencegah kirim ganda token sama. */
    const inFlightRef = useRef<Set<string>>(new Set());
    /** Token berhasil terakhir beserta waktunya, untuk penjaga SAME_CARD_MS. */
    const recentRef = useRef<Map<string, number>>(new Map());
    const resultTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const readerId = 'public-qr-reader';

    const blocked = !school.is_active || Boolean(disabledNotice);

    const csrfToken =
        typeof document !== 'undefined'
            ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''
            : '';

    /** Token CSRF yang sedang berlaku. Bisa diganti saat sesinya diperbarui. */
    const tokenCsrfRef = useRef(csrfToken);

    const kirim = useCallback(
        (token: string, csrf: string) =>
            fetch(scanUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ token }),
            }),
        [scanUrl],
    );

    // Live clock
    useEffect(() => {
        const tick = () => {
            const now = new Date();
            setClock(now.toLocaleTimeString('id-ID'));
            setToday(now.toLocaleDateString('id-ID', DATE_FMT));
        };
        tick();
        const id = setInterval(tick, 1000);
        return () => clearInterval(id);
    }, []);

    // Fullscreen state tracking
    useEffect(() => {
        const handler = () => setIsFullscreen(Boolean(layarPenuhSekarang()));
        document.addEventListener('fullscreenchange', handler);
        document.addEventListener('webkitfullscreenchange', handler);

        return () => {
            document.removeEventListener('fullscreenchange', handler);
            document.removeEventListener('webkitfullscreenchange', handler);
        };
    }, []);

    /**
     * Tombolnya disembunyikan kalau peramban tidak punya layar penuh sama
     * sekali.
     *
     * Safari di iPhone tidak mengenal `requestFullscreen` maupun
     * `fullscreenElement` — bukan ditolak, memang tidak ada. Tombolnya
     * terpasang, ditekan, dan tidak terjadi apa-apa. Tombol mati lebih buruk
     * daripada tombol yang tidak ada: yang pertama membuat orang menekannya
     * berkali-kali lalu menyimpulkan halamannya rusak.
     */
    const tombolPenuhRef = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        const akar = document.documentElement as HTMLElement & {
            webkitRequestFullscreen?: () => Promise<void> | void;
        };
        const ada =
            typeof akar.requestFullscreen === 'function' || typeof akar.webkitRequestFullscreen === 'function';

        // Atribut disetel langsung, bukan lewat state: SSR tidak bisa tahu
        // peramban mana yang akan menerima halaman ini, dan menebaknya saat
        // render menghasilkan ketidakcocokan hidrasi.
        if (!ada) {
            tombolPenuhRef.current?.setAttribute('hidden', '');
        }
    }, []);

    const toggleFullscreen = useCallback(() => {
        const akar = document.documentElement as HTMLElement & {
            webkitRequestFullscreen?: () => Promise<void> | void;
        };
        const dok = document as Document & {
            webkitExitFullscreen?: () => Promise<void> | void;
        };

        if (layarPenuhSekarang()) {
            const keluar = dok.exitFullscreen?.bind(dok) ?? dok.webkitExitFullscreen?.bind(dok);
            void Promise.resolve(keluar?.()).catch(() => {});

            return;
        }

        const masuk = akar.requestFullscreen?.bind(akar) ?? akar.webkitRequestFullscreen?.bind(akar);
        void Promise.resolve(masuk?.()).catch(() => {});
    }, []);

    const submitScan = useCallback(
        async (rawToken: string) => {
            const token = rawToken.trim();
            if (!token || token.length < 3) return;

            if (inFlightRef.current.has(token)) return;

            const now = Date.now();
            const recent = recentRef.current;
            recent.forEach((at, key) => {
                if (now - at > SAME_CARD_MS) recent.delete(key);
            });
            if (recent.has(token)) return;

            inFlightRef.current.add(token);
            setLastProbe({ len: token.length, awal: token.slice(0, 3), akhir: token.slice(-3) });

            try {
                let res = await kirim(token, tokenCsrfRef.current);

                /*
                 * 419 = token CSRF kedaluwarsa, bukan kartu yang salah.
                 *
                 * Halaman gerbang dibiarkan terbuka sepanjang hari. Token CSRF
                 * ikut tercetak SEKALI saat halaman dimuat, dan sesi berakhir
                 * setelah menganggur — jeda antara absen masuk pagi dan absen
                 * pulang siang sudah cukup. Sesudah itu SETIAP tempelan kartu
                 * dijawab 419 berisi HTML, `res.json()` melempar, dan layarnya
                 * bilang "Gagal menghubungi server" — kalimat yang mengirim
                 * orang memeriksa jaringan dan kabel, bukan memuat ulang
                 * halaman.
                 *
                 * Diambil token baru lalu dicoba sekali lagi. Sekali, bukan
                 * berulang: kalau yang baru pun ditolak, masalahnya bukan token.
                 */
                if (res.status === 419) {
                    const segar = await ambilTokenCsrf();

                    if (segar) {
                        tokenCsrfRef.current = segar;
                        res = await kirim(token, segar);
                    }
                }

                if (!mountedRef.current) return;

                if (res.status === 419) {
                    setLastResult({
                        success: false,
                        message: 'Sesi halaman ini sudah kedaluwarsa. Muat ulang halaman gerbang.',
                        student: null,
                    });
                    playErrorSound('Sesi kedaluwarsa, muat ulang halaman');

                    return;
                }

                // Jawaban yang bukan JSON — 500, 503, atau halaman perantara
                // jaringan sekolah — membuat `json()` melempar dan menyamar
                // jadi "gagal menghubungi server".
                const jenis = res.headers.get('content-type') ?? '';

                if (!jenis.includes('json')) {
                    setLastResult({
                        success: false,
                        message: `Server menjawab tidak seperti biasanya (kode ${res.status}). Coba lagi sebentar.`,
                        student: null,
                    });
                    playErrorSound('Server bermasalah');

                    return;
                }

                const data: ScanResult = await res.json();
                if (!mountedRef.current) return;

                // Hanya yang berhasil yang ditahan. Kartu yang ditolak boleh
                // ditempel ulang saat itu juga — dulu ia ikut terkunci dan
                // operator mengira alatnya rusak.
                if (data.success) recent.set(token, Date.now());

                setLastResult(data);
                setScanLog((prev) =>
                    [
                        {
                            id: ++logId,
                            student: data.student,
                            success: data.success,
                            message: data.message,
                            time: new Date().toLocaleTimeString('id-ID'),
                        },
                        ...prev,
                    ].slice(0, 50),
                );

                if (data.success) playSuccessSound(data.student?.full_name);
                else playErrorSound(data.message);
            } catch {
                if (!mountedRef.current) return;
                setLastResult({ success: false, message: 'Gagal menghubungi server.', student: null });
                playErrorSound('Gagal menghubungi server');
            } finally {
                inFlightRef.current.delete(token);
            }

            if (resultTimeout.current) clearTimeout(resultTimeout.current);
            resultTimeout.current = setTimeout(() => {
                if (mountedRef.current) setLastResult(null);
            }, RESULT_MS);
        },
        [kirim],
    );

    // ---- Camera (live QR) ----

    /**
     * Kotak pindai, dihitung dari ukuran viewfinder yang sebenarnya.
     *
     * Dulu dipatok 260x260. Di layar ponsel yang lebih sempit dari itu,
     * html5-qrcode menolak memulai dengan "qrbox dimensions should not be
     * greater than the video width/height" — kamera tidak menyala sama sekali,
     * dan di Windows angka itu tidak pernah tercapai sehingga tidak pernah
     * terlihat.
     */
    const kotakPindai = useCallback((lebar: number, tinggi: number) => {
        const kecil = Math.min(lebar, tinggi);
        const sisi = Math.min(kecil, Math.max(120, Math.floor(kecil * 0.75)));

        return { width: sisi, height: sisi };
    }, []);

    const stopScanner = useCallback(async () => {
        if (scannerRef.current) {
            try {
                if (scannerRef.current.isScanning) await scannerRef.current.stop();
            } catch {
                /* noop */
            }
            try {
                scannerRef.current.clear();
            } catch {
                /* noop */
            }
            scannerRef.current = null;
        }
    }, []);

    const startWithCamera = useCallback(
        async (cameraId: string) => {
            setCameraStatus('loading');
            setCameraError(null);
            await stopScanner();

            const el = document.getElementById(readerId);
            if (!el) {
                setCameraError('Elemen scanner tidak ditemukan.');
                setCameraStatus('error');
                return;
            }
            el.innerHTML = '';

            /*
             * Tidak ada `mediaDevices` sama sekali — dibedakan dari izin yang
             * ditolak, karena tindakannya berbeda jauh.
             *
             * Tiga sebab: halaman diakses lewat http biasa (API kamera cuma
             * hidup di konteks aman), peramban terlalu tua, atau tautannya
             * dibuka di dalam WhatsApp. Yang ketiga paling sering di sini, dan
             * satu-satunya jalan keluarnya membuka tautan di peramban
             * sungguhan — bukan menekan "Coba Lagi" berulang kali.
             */
            if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
                setCameraError(
                    peramban_dalam_aplikasi()
                        ? 'Kamera diblokir karena halaman ini dibuka di dalam aplikasi lain. Ketuk menu ⋮ lalu "Buka di peramban", atau salin tautannya ke Chrome/Safari.'
                        : window.isSecureContext === false
                          ? 'Kamera hanya bisa dipakai lewat alamat https. Buka halaman ini dengan https://.'
                          : 'Peramban ini tidak mendukung kamera. Gunakan Chrome atau Safari terbaru, atau pakai barcode gun.',
                );
                setCameraStatus('error');

                return;
            }

            try {
                /*
                 * Dicoba berurutan sampai ada yang menyala, bukan sekali lalu
                 * menyerah.
                 *
                 * `deviceId: { exact: ... }` adalah permintaan yang keras: id
                 * kamera di Android berganti antar sesi dan sebagian peramban
                 * bawaan OEM menolaknya dengan OverconstrainedError. Di Windows
                 * id-nya stabil, jadi jalur itu tidak pernah gagal dan
                 * kerapuhannya tidak pernah terlihat.
                 *
                 * `aspectRatio: 1` juga dibuang: memaksa 1:1 ditolak banyak
                 * kamera ponsel, sementara webcam desktop menerimanya.
                 */
                const kandidat: MediaTrackConstraints[] = [
                    ...(cameraId ? [{ deviceId: { exact: cameraId } }] : []),
                    { facingMode: { ideal: 'environment' } },
                    { facingMode: 'user' },
                ];

                const setelan = {
                    fps: 15,
                    qrbox: kotakPindai,
                    disableFlip: false,
                    experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                };

                let terakhir: unknown = null;
                let nyala = false;

                for (const constraint of kandidat) {
                    /*
                     * Instance BARU tiap percobaan.
                     *
                     * Html5Qrcode punya mesin keadaan internal, dan memanggil
                     * `start()` lagi pada instance yang percobaan sebelumnya
                     * baru saja gagal menghasilkan "Cannot transition to a new
                     * state, already under transition" — galat yang menutupi
                     * sebab aslinya dan membuat tangga percobaan ini tidak ada
                     * gunanya. Terlihat pertama kali di harness CDP, bukan di
                     * lapangan.
                     */
                    const scanner = new Html5Qrcode(readerId);
                    scannerRef.current = scanner;

                    try {
                        await scanner.start(constraint, setelan, (text) => submitScan(text), () => {});
                        nyala = true;
                        break;
                    } catch (err) {
                        terakhir = err;

                        try {
                            if (scanner.isScanning) {
                                await scanner.stop();
                            }
                            scanner.clear();
                        } catch {
                            /* noop */
                        }

                        scannerRef.current = null;
                        el.innerHTML = '';
                    }
                }

                if (!nyala) {
                    throw terakhir instanceof Error ? terakhir : new Error(String(terakhir));
                }

                if (mountedRef.current) setCameraStatus('scanning');
            } catch (err) {
                if (!mountedRef.current) return;
                const msg = err instanceof Error ? err.message : String(err);

                if (msg.includes('Permission') || msg.includes('NotAllowed')) {
                    // Di dalam WhatsApp, iOS menolak dengan galat yang sama
                    // persis seperti izin yang ditolak pengguna — padahal
                    // tidak ada izin yang bisa diberikan di sana.
                    setCameraError(
                        peramban_dalam_aplikasi()
                            ? 'Kamera diblokir karena halaman ini dibuka di dalam aplikasi lain. Ketuk menu ⋮ lalu "Buka di peramban", atau salin tautannya ke Chrome/Safari.'
                            : 'Akses kamera ditolak. Izinkan kamera di pengaturan browser, lalu tekan Coba Lagi.',
                    );
                } else if (msg.includes('NotFound') || msg.includes('not found')) {
                    setCameraError('Kamera tidak ditemukan. Gunakan barcode gun / input manual.');
                } else if (msg.includes('NotReadable') || msg.includes('TrackStart')) {
                    // Kamera dipegang aplikasi lain. Sering terjadi di PC
                    // gerbang yang juga membuka aplikasi kamera bawaan.
                    setCameraError('Kamera sedang dipakai aplikasi lain. Tutup aplikasi kamera itu, lalu tekan Coba Lagi.');
                } else {
                    setCameraError(`Gagal memulai kamera: ${msg}`);
                }

                setCameraStatus('error');
            }
        },
        [kotakPindai, stopScanner, submitScan],
    );

    // Init cameras + auto-start
    useEffect(() => {
        if (blocked) return;

        mountedRef.current = true;

        async function init() {
            await new Promise((r) => setTimeout(r, 200));
            if (!mountedRef.current) return;
            try {
                const devices = await Html5Qrcode.getCameras();
                if (!mountedRef.current) return;

                const cams = devices.map((d) => ({ id: d.id, label: d.label || `Kamera ${d.id.slice(0, 6)}` }));
                setCameras(cams);

                /*
                 * Kamera luar dulu (rig gerbang di PC), lalu yang menghadap ke
                 * belakang (ponsel).
                 *
                 * Yang DIBUANG: `cams[cams.length - 1]` sebagai jalan terakhir.
                 * Ponsel Android sekarang memaparkan empat sampai enam kamera —
                 * ultrawide, telefoto, sensor kedalaman, monokrom — dan yang
                 * terakhir dalam daftar sering salah satu dari itu. Kameranya
                 * menyala, gambarnya muncul, tapi tidak bisa fokus sedekat
                 * kartu, jadi QR tidak pernah terbaca dan layar terlihat
                 * seolah kartunya yang bermasalah.
                 *
                 * Tanpa pilihan yang meyakinkan, lebih baik menyerahkannya ke
                 * `facingMode: environment` di `startWithCamera` — peramban
                 * yang paling tahu kamera mana yang utama.
                 */
                const eksternal = cams.find((c) => /usb|iware|external|web ?cam|hd/i.test(c.label));
                const belakang = cams.find((c) => /back|rear|environment|belakang/i.test(c.label));
                const pilih = eksternal ?? belakang ?? null;

                if (pilih) {
                    setSelectedCamera(pilih.id);
                }

                await startWithCamera(pilih?.id ?? '');
            } catch {
                if (!mountedRef.current) return;

                /*
                 * Daftar kamera gagal diambil, TAPI itu belum tentu berarti
                 * kameranya tidak bisa dipakai.
                 *
                 * `getCameras()` memanggil `enumerateDevices()`, yang di
                 * sebagian WebView Android lama tidak ada atau mengembalikan
                 * daftar kosong walau `getUserMedia` bekerja normal. Dulu
                 * kegagalan ini langsung jadi layar merah; sekarang ia tetap
                 * mencoba menyalakan kamera lewat `facingMode`, dan barulah
                 * menyerah kalau itu pun gagal.
                 */
                await startWithCamera('');
            }
        }

        init();

        return () => {
            mountedRef.current = false;
            stopScanner();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [blocked]);

    // ---- Barcode gun (global keyboard listener) ----
    useEffect(() => {
        if (blocked) return;

        let buffer = '';
        let firstAt = 0;
        let lastAt = 0;
        let timer: ReturnType<typeof setTimeout> | null = null;

        function reset() {
            buffer = '';
            firstAt = 0;
            lastAt = 0;
            if (barcodeInputRef.current) barcodeInputRef.current.value = '';
        }

        /**
         * Kirim apa yang sudah terkumpul.
         *
         * Kotak isian adalah sumber UTAMA, bukan cadangan: elemen input tidak
         * punya timer dan tidak bisa terpotong, sedangkan buffer bisa terpangkas
         * kalau satu keystroke tertunda melewati KEY_IDLE_MS di perangkat lemot.
         * Buffer hanya dipakai ketika fokus tidak berada di kotak itu — misalnya
         * operator baru saja mengklik di tempat lain.
         */
        function flush() {
            const dariInput = barcodeInputRef.current?.value?.trim() ?? '';
            const token = dariInput !== '' ? dariInput : buffer;

            reset();

            if (token.length >= 3) submitScan(token);
        }

        function onKeyDown(e: KeyboardEvent) {
            const tag = (e.target as HTMLElement)?.tagName;
            if (tag === 'TEXTAREA' || tag === 'SELECT') return;
            if (tag === 'INPUT' && (e.target as HTMLInputElement) !== barcodeInputRef.current) return;

            if (e.key === 'Enter' || e.code === 'NumpadEnter') {
                e.preventDefault();
                if (timer) clearTimeout(timer);
                timer = null;
                flush();

                return;
            }

            if (e.key.length === 1) {
                const now = Date.now();
                if (buffer === '') firstAt = now;
                lastAt = now;
                buffer = (buffer + e.key).slice(-MAX_BUFFER);

                if (timer) clearTimeout(timer);
                timer = setTimeout(() => {
                    timer = null;

                    // Banyak pembaca RFID tidak mengirim Enter sama sekali —
                    // ketikannya berhenti begitu saja. Dulu buffer-nya justru
                    // DIBUANG di sini, jadi kartu semacam itu tidak pernah
                    // terkirim tanpa operator menekan Enter sendiri.
                    const perKey = buffer.length > 1 ? (lastAt - firstAt) / (buffer.length - 1) : Infinity;

                    if (buffer.length >= MIN_TOKEN && perKey <= MACHINE_MS_PER_KEY) {
                        flush();
                    } else {
                        reset();
                    }
                }, KEY_IDLE_MS);
            }
        }

        window.addEventListener('keydown', onKeyDown);
        return () => {
            window.removeEventListener('keydown', onKeyDown);
            if (timer) clearTimeout(timer);
        };
    }, [submitScan, blocked]);

    function handleManualSubmit(e: React.FormEvent) {
        e.preventDefault();
        const val = barcodeInputRef.current?.value?.trim();
        if (val && val.length >= 3) submitScan(val);
        if (barcodeInputRef.current) barcodeInputRef.current.value = '';
    }

    // ---- Sekolah nonaktif / fitur belum dinyalakan ----
    if (blocked) {
        return (
            <div className="flex min-h-screen min-h-dvh flex-col items-center justify-center bg-slate-50 px-6 text-center">
                <div className="flex size-16 items-center justify-center rounded-2xl bg-slate-200 text-slate-500">
                    <SchoolIcon className="size-8" />
                </div>
                <h1 className="mt-5 text-2xl font-bold text-slate-800">{school.name}</h1>
                <p className="mt-2 max-w-md text-slate-500">
                    {disabledNotice ?? 'Halaman absensi sekolah ini sedang tidak aktif.'}
                </p>
            </div>
        );
    }

    const success = lastResult?.success && lastResult.student;
    const badge = resultBadge(lastResult?.student?.type);

    return (
        <div className="relative flex min-h-screen min-h-dvh flex-col bg-gradient-to-b from-slate-50 via-white to-slate-100 text-slate-800">
            {/* Fullscreen toggle — samar saat fullscreen, hilang di iPhone
                yang memang tidak punya API-nya. */}
            <button
                ref={tombolPenuhRef}
                onClick={toggleFullscreen}
                title={isFullscreen ? 'Keluar layar penuh' : 'Layar penuh'}
                className={`fixed right-4 top-4 z-50 flex size-10 items-center justify-center rounded-full border border-slate-200 bg-white/80 text-slate-600 shadow-sm backdrop-blur transition-all hover:bg-white hover:text-slate-900 ${
                    isFullscreen ? 'opacity-15 hover:opacity-100' : 'opacity-100'
                }`}
            >
                {isFullscreen ? <Minimize className="size-5" /> : <Maximize className="size-5" />}
            </button>

            <main className="mx-auto flex w-full max-w-2xl flex-1 flex-col items-center gap-6 px-5 py-8">
                {/* Brand + Clock (no navbar) */}
                <div className="flex flex-col items-center gap-4 text-center">
                    <div className="flex items-center gap-2.5">
                        {school.logo_url ? (
                            <img src={school.logo_url} alt={school.name} className="size-9 rounded-xl object-contain" />
                        ) : (
                            <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white">
                                <SchoolIcon className="size-5" />
                            </div>
                        )}
                        <div className="text-left leading-tight">
                            <p className="text-sm font-bold text-slate-800">{school.name}</p>
                            <p className="text-xs text-slate-400">{tagline}</p>
                        </div>
                    </div>

                    <div className="flex flex-col items-center">
                        <div className="flex items-center gap-2 font-mono text-5xl font-bold tabular-nums tracking-tight text-slate-900 sm:text-6xl">
                            <Clock className="size-8 text-blue-500 sm:size-9" strokeWidth={2.2} />
                            {clock}
                        </div>
                        <p className="mt-1 text-sm font-medium capitalize text-slate-500">{today}</p>
                    </div>
                </div>

                {/* Camera hero */}
                <div className="relative w-full overflow-hidden rounded-3xl border border-slate-200 bg-slate-950 shadow-xl">
                    <div className="relative aspect-square w-full sm:aspect-[4/3]">
                        {/* `object-contain`, BUKAN `object-cover`.
                            `object-cover` memangkas tepi video di layar
                            sedangkan html5-qrcode tetap memindai bingkai utuh
                            dari stream. Di ponsel potret selisihnya besar:
                            operator menaruh QR tepat di dalam kotak hijau,
                            dan kotak itu bukan yang dibaca. Kamera menyala,
                            gambar jernih, kartu tidak pernah terbaca — tanpa
                            satu pun petunjuk kenapa. */}
                        <div id={readerId} className="size-full [&>video]:!size-full [&>video]:!object-contain" />

                        {/*
                            Bingkai pindai sengaja TIDAK digambar di sini.

                            html5-qrcode sudah menggambar kotaknya sendiri, dan
                            kotak itu menurut definisinya adalah area yang
                            benar-benar dibaca. Dulu ada empat sudut hijau
                            berukuran tetap di atasnya — hiasan yang tidak
                            terhubung ke apa pun, dan di ponsel ia menunjuk
                            tempat yang salah sehingga operator mengarahkan
                            kartu ke luar area baca. Percobaan menyamakan
                            ukurannya menghasilkan DUA bingkai bertumpuk yang
                            tetap tidak sejajar; menghapusnya yang benar.
                        */}
                        {cameraStatus === 'scanning' && !lastResult && (
                            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/90 to-transparent p-4 text-center">
                                <p className="text-sm font-medium text-slate-100">Arahkan QR Code siswa ke kamera</p>
                                <p className="mt-0.5 text-xs text-slate-400">
                                    {hint ?? 'atau tembak QR-nya dengan barcode gun — otomatis terdeteksi'}
                                </p>
                            </div>
                        )}

                        {cameraStatus === 'loading' && (
                            <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-slate-950">
                                <Loader2 className="size-10 animate-spin text-slate-500" />
                                <p className="text-sm text-slate-400">Membuka kamera...</p>
                            </div>
                        )}

                        {cameraStatus === 'error' && (
                            <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-slate-950 px-6 text-center">
                                <Camera className="size-10 text-slate-500" />
                                <p className="text-sm text-slate-300">{cameraError}</p>
                                <button
                                    onClick={() => startWithCamera(selectedCamera)}
                                    className="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/20"
                                >
                                    <RefreshCw className="size-4" /> Coba Lagi
                                </button>
                                <p className="mt-1 text-xs text-slate-500">Barcode gun tetap berfungsi tanpa kamera.</p>
                            </div>
                        )}

                        {/* Result overlay */}
                        {lastResult && (
                            <div className="absolute inset-0 z-10 flex items-center justify-center bg-slate-950/85 p-5 backdrop-blur-sm">
                                {success && lastResult.student ? (
                                    <div className="max-h-full w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 text-slate-900 shadow-2xl">
                                        {/* Header: foto + nama + badge */}
                                        <div className="flex gap-4">
                                            {/* Bentuk pas foto 3:4 dengan object-contain — kotak
                                                + object-cover dulu memangkas kepala anak. */}
                                            {lastResult.student.photo_url ? (
                                                <img
                                                    src={lastResult.student.photo_url}
                                                    alt={lastResult.student.full_name}
                                                    className="aspect-[3/4] w-24 shrink-0 rounded-2xl border-4 border-emerald-300 bg-emerald-50 object-contain shadow"
                                                />
                                            ) : (
                                                <div className="flex aspect-[3/4] w-24 shrink-0 items-center justify-center rounded-2xl border-4 border-emerald-300 bg-emerald-50">
                                                    <User className="size-12 text-emerald-400" />
                                                </div>
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <span
                                                    className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold text-white ${badge.className}`}
                                                >
                                                    <badge.Icon className="size-3" />
                                                    {lastResult.student.type_label}
                                                </span>
                                                <h3 className="mt-1.5 text-xl font-bold leading-tight">{lastResult.student.full_name}</h3>
                                                <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                                    <span className="flex items-center gap-1 font-bold text-emerald-700">
                                                        <CheckCircle2 className="size-4" /> {lastResult.student.status}
                                                    </span>
                                                    <span className="flex items-center gap-1 font-mono font-semibold text-slate-500">
                                                        <Clock className="size-4" /> {lastResult.student.time}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Grid detail siswa */}
                                        <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2.5 border-t border-slate-100 pt-4 text-sm">
                                            <DetailField label="NIS" value={lastResult.student.nis} />
                                            <DetailField label="No. Absen" value={lastResult.student.no_absen} />
                                            <DetailField label="Kelas" value={lastResult.student.classroom} full />
                                        </dl>
                                    </div>
                                ) : (
                                    <div className="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
                                        <XCircle className="mx-auto size-12 text-red-500" />
                                        <p className="mt-3 text-lg font-bold text-red-600">{lastResult.message}</p>
                                        {lastProbe && (
                                            <p className="mt-2 font-mono text-xs text-slate-400">
                                                terbaca {lastProbe.len} karakter · {lastProbe.awal}…{lastProbe.akhir}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Camera selector */}
                    {cameras.length > 1 && (
                        <div className="flex items-center gap-2 border-t border-white/10 px-4 py-3">
                            <Camera className="size-4 shrink-0 text-slate-400" />
                            <select
                                value={selectedCamera}
                                onChange={(e) => {
                                    setSelectedCamera(e.target.value);
                                    startWithCamera(e.target.value);
                                }}
                                className="flex-1 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200"
                            >
                                {cameras.map((cam) => (
                                    <option key={cam.id} value={cam.id} className="bg-slate-800">
                                        {cam.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>

                {/* Manual / gun input */}
                <form onSubmit={handleManualSubmit} className="w-full">
                    <input
                        ref={barcodeInputRef}
                        type="text"
                        inputMode="text"
                        autoComplete="off"
                        autoFocus
                        placeholder="Tembak barcode gun ke QR Code siswa..."
                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-center font-mono tracking-wide text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/30"
                    />
                </form>

                {/* Recent log */}
                {scanLog.length > 0 && (
                    <div className="w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-100 px-5 py-3">
                            <h3 className="text-sm font-bold text-slate-600">Riwayat Scan ({scanLog.length})</h3>
                        </div>
                        <div className="max-h-64 divide-y divide-slate-100 overflow-y-auto">
                            {scanLog.map((entry) => (
                                <div key={entry.id} className="flex items-center gap-3 px-5 py-3">
                                    {entry.success && entry.student?.photo_url ? (
                                        <img
                                            src={entry.student.photo_url}
                                            alt=""
                                            className="aspect-[3/4] w-9 shrink-0 rounded-lg bg-slate-100 object-contain"
                                        />
                                    ) : (
                                        <div
                                            className={`flex aspect-[3/4] w-9 shrink-0 items-center justify-center rounded-lg ${
                                                entry.success ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-500'
                                            }`}
                                        >
                                            {entry.success ? <CheckCircle2 className="size-5" /> : <XCircle className="size-5" />}
                                        </div>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-slate-800">
                                            {entry.student?.full_name || entry.message}
                                        </p>
                                        <p className="truncate text-xs text-slate-400">
                                            {entry.student?.classroom}
                                            {entry.student?.type_label ? ` · ${entry.student.type_label}` : ''}
                                            {entry.student?.status ? ` · ${entry.student.status}` : ''}
                                        </p>
                                    </div>
                                    <span className="shrink-0 font-mono text-xs text-slate-400">{entry.time}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </main>
        </div>
    );
}

function resultBadge(type?: string) {
    if (type === 'PRAYER') {
        return { Icon: MoonStar, className: 'bg-emerald-600' };
    }
    if (type === 'LIBRARY') {
        return { Icon: BookOpen, className: 'bg-indigo-600' };
    }
    if (type === 'CHECK_OUT') {
        return { Icon: LogOut, className: 'bg-orange-500' };
    }

    return { Icon: LogIn, className: 'bg-emerald-500' };
}

function DetailField({ label, value, full = false }: { label: string; value: string | null; full?: boolean }) {
    if (!value) return null;
    return (
        <div className={full ? 'col-span-2' : ''}>
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</dt>
            <dd className="font-semibold text-slate-700">{value}</dd>
        </div>
    );
}
