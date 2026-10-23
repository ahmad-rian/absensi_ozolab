/**
 * Kamera terbaik untuk memindai kartu dari jarak dekat, atau null bila tidak
 * ada pilihan yang meyakinkan (biarkan `facingMode: environment` memilih).
 *
 * iPhone memaparkan beberapa kamera belakang. "Back Camera" (lensa utama) di
 * iPhone Pro tidak bisa fokus lebih dekat dari ±20 cm, jadi QR kartu tidak
 * pernah terbaca walau gambarnya muncul. Kamera VIRTUAL "Back Triple Camera" /
 * "Back Dual Wide Camera" otomatis pindah ke lensa makro saat kartu didekatkan
 * — itu yang dipakai aplikasi Kamera bawaan. Ultra wide, telefoto, dan sensor
 * kedalaman tidak pernah dipilih.
 */
export function pilihKamera(
    cams: { id: string; label: string }[],
): string | null {
    const skor = (label: string): number => {
        const l = label.toLowerCase();

        if (/front|depan|user|facetime/.test(l)) {
return -1;
}

        if (/telephoto|tele\b|depth|infrared|ir\b|monochrome/.test(l)) {
return -1;
}

        if (/usb|iware|external|web ?cam|\bhd\b/.test(l)) {
return 100;
}

        if (/triple/.test(l)) {
return 90;
}

        if (/dual wide/.test(l)) {
return 80;
}

        if (/dual/.test(l)) {
return 70;
}

        if (/ultra ?wide|ultrawide|macro/.test(l)) {
return -1;
}

        if (/back|rear|environment|belakang/.test(l)) {
return 50;
}

        return 0;
    };

    const terbaik = cams
        .map((c) => ({ id: c.id, nilai: skor(c.label) }))
        .filter((c) => c.nilai > 0)
        .sort((a, b) => b.nilai - a.nilai)[0];

    return terbaik?.id ?? null;
}
