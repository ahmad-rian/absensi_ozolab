/**
 * Kontrak statistik siswa. Bentuk `StatsBlock` sengaja sama untuk absensi
 * sekolah maupun tiap jenis sholat, supaya satu komponen panel bisa melayani
 * ketiga tab tanpa percabangan.
 */

export type DailyPoint = { date: string } & Record<string, number>;

export type WeekdayPoint = {
    weekday: string;
    effective: number;
    hadir: number;
    terlambat: number;
    izin: number;
    sakit: number;
    alpa: number;
    rate: number;
    late_rate: number;
};

export type WeekdayBlock = {
    series: WeekdayPoint[];
    worst_day: string | null;
};

export type MonthlyPoint = {
    month: string;
    hadir: number;
    terlambat: number;
    rate: number;
};

export type HeatmapDay = {
    date: string;
    state: string;
};

export type StreakStats = {
    current_length: number;
    current_kind: 'hadir' | 'tidak_hadir' | null;
    longest_present: number;
    longest_absent: number;
    last_absent_date: string | null;
};

export type PunctualityStats = {
    avg_check_in: string | null;
    earliest: string | null;
    latest: string | null;
    avg_late_minutes?: number | null;
    deadline?: string | null;
};

export type PeerComparison = {
    class_rate: number | null;
    class_late_rate: number | null;
    class_size: number;
    class_name: string | null;
};

export type HistoryRow = {
    id: string;
    date: string;
    status: string;
    status_label: string;
    time: string | null;
    device_id: string | null;
    type?: string;
    type_label?: string;
};

export type AttendanceSummary = {
    hadir: number;
    terlambat: number;
    izin: number;
    sakit: number;
    alpa: number;
    tanpa_keterangan: number;
    effective_days: number;
    recorded_days: number;
    rate: number;
    punctual_rate: number;
};

export type AttendanceStats = {
    summary: AttendanceSummary;
    punctuality: PunctualityStats;
    by_weekday: WeekdayBlock;
    streaks: StreakStats;
    monthly: MonthlyPoint[];
    heatmap: HeatmapDay[];
    comparison: PeerComparison;
    daily: DailyPoint[];
    recent: HistoryRow[];
};

export type PrayerSummary = {
    hadir: number;
    tidak_hadir: number;
    effective_days: number;
    rate: number;
};

export type PrayerTypeStats = {
    type: string;
    label: string;
    short_label: string;
    enabled: boolean;
    window: string;
    summary: PrayerSummary;
    punctuality: PunctualityStats;
    streaks: StreakStats;
    by_weekday: WeekdayBlock;
    heatmap: HeatmapDay[];
    daily: DailyPoint[];
    recent: HistoryRow[];
};

export type PrayerStats = {
    enabled: boolean;
    covered: boolean;
    /** null = ikut aturan sekolah; true/false = dioverride per siswa. */
    opt_in: boolean | null;
    school_includes_all: boolean;
    religion_label: string | null;
    window: string | null;
    summary: PrayerSummary & { opportunities: number };
    daily: DailyPoint[];
    recent: HistoryRow[];
    types: PrayerTypeStats[];
};

export type RangeFilters = {
    start: string;
    end: string;
    /** Dirakit server supaya layar, CSV, dan PDF tidak pernah berbeda format. */
    label: string;
};
