/*
 * Bahasa visual halaman publik (login, pendaftaran, izin): langit lembut,
 * kartu kaca, field abu tanpa garis, tombol gelap. Disatukan di sini supaya
 * halaman-halaman itu tidak bisa berbeda diam-diam.
 */

/** Kartu kaca di atas langit. */
export const KELAS_KARTU_KACA =
    'rounded-3xl border border-white/80 bg-[linear-gradient(180deg,rgba(232,244,253,0.92)_0%,rgba(255,255,255,0.96)_42%)] shadow-[0_24px_60px_-20px_rgba(30,64,110,0.35)] backdrop-blur-xl dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(24,40,62,0.92)_0%,rgba(15,23,36,0.96)_42%)] dark:shadow-black/40';

/** Tombol utama gelap (terbalik putih di mode gelap). */
export const KELAS_TOMBOL_GELAP =
    'rounded-[12px] bg-[linear-gradient(180deg,#3A3F47_0%,#15181D_100%)] font-semibold text-white shadow-[0_8px_20px_-8px_rgba(15,18,24,0.6),inset_0_1px_0_rgba(255,255,255,0.12)] hover:brightness-110 disabled:opacity-50 dark:bg-[linear-gradient(180deg,#FFFFFF_0%,#E4E8EE_100%)] dark:text-zinc-900';

/** Field abu lembut tanpa garis. */
export const KELAS_FIELD_LEMBUT =
    'rounded-[12px] border-transparent bg-zinc-100/90 shadow-none focus-visible:bg-white dark:bg-white/5 dark:focus-visible:bg-white/10';

/** Gumpalan awan di bawah layar: tiap item satu lingkaran blur. */
const AWAN = [
    { left: '-8%', bottom: '-14%', size: '42rem' },
    { left: '22%', bottom: '-22%', size: '38rem' },
    { left: '55%', bottom: '-18%', size: '40rem' },
    { left: '80%', bottom: '-12%', size: '34rem' },
    { left: '8%', bottom: '4%', size: '22rem' },
    { left: '68%', bottom: '6%', size: '24rem' },
];

/** Lengkung tipis di belakang kartu, seperti riak dari tengah layar. */
const LENGKUNG = [380, 560, 760];

export function LatarLangit({ tetap = false }: { tetap?: boolean }) {
    return (
        <div
            aria-hidden="true"
            className={`pointer-events-none inset-0 -z-10 overflow-hidden ${tetap ? 'fixed' : 'absolute'}`}
        >
            <div className="absolute inset-0 bg-[linear-gradient(180deg,#8EC2EA_0%,#B9DAF3_38%,#E4F0FA_72%,#F4F8FC_100%)] dark:bg-[linear-gradient(180deg,#07111F_0%,#0C1D33_45%,#132A45_100%)]" />

            <svg
                className="absolute top-1/2 left-1/2 h-[1600px] w-[1600px] -translate-x-1/2 -translate-y-1/2"
                viewBox="0 0 1600 1600"
                fill="none"
            >
                {LENGKUNG.map((r) => (
                    <circle
                        key={r}
                        cx="800"
                        cy="800"
                        r={r}
                        className="stroke-white/45 dark:stroke-white/[0.06]"
                        strokeWidth="1"
                    />
                ))}
            </svg>

            {AWAN.map((a) => (
                <div
                    key={`${a.left}-${a.bottom}`}
                    className="absolute rounded-full bg-white/80 blur-3xl dark:bg-sky-200/[0.06]"
                    style={{
                        left: a.left,
                        bottom: a.bottom,
                        width: a.size,
                        height: `calc(${a.size} * 0.55)`,
                    }}
                />
            ))}
        </div>
    );
}
