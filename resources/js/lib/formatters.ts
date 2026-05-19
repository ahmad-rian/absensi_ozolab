/**
 * Format number as Indonesian Rupiah.
 */
export function formatRupiah(amount: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

/**
 * Format date string to Indonesian locale.
 */
export function formatDate(date: string | Date, options?: Intl.DateTimeFormatOptions): string {
    const d = typeof date === 'string' ? new Date(date) : date;

    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        ...options,
    }).format(d);
}

/**
 * Format date to short format: "19 Mei 2026".
 */
export function formatDateShort(date: string | Date): string {
    const d = typeof date === 'string' ? new Date(date) : date;

    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(d);
}

/**
 * Format time string to HH:mm.
 */
export function formatTime(date: string | Date): string {
    const d = typeof date === 'string' ? new Date(date) : date;

    return new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(d);
}

/**
 * Format NIS with padding: "20250001".
 */
export function formatNis(nis: string): string {
    return nis.padStart(8, '0');
}

/**
 * Format number with Indonesian thousand separator.
 */
export function formatNumber(num: number): string {
    return new Intl.NumberFormat('id-ID').format(num);
}

/**
 * Format day name in Indonesian.
 */
export function formatDayName(date: string | Date): string {
    const d = typeof date === 'string' ? new Date(date) : date;

    return new Intl.DateTimeFormat('id-ID', { weekday: 'long' }).format(d);
}
