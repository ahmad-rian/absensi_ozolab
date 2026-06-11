<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Notifikasi Kehadiran</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:'Segoe UI',Helvetica,Arial,sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(15,23,42,0.08);">
                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#1e3a8a; padding:28px 32px;">
                            <p style="margin:0; font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#bfdbfe;">Notifikasi {{ $vars['jenis'] ?? 'Kehadiran' }}</p>
                            <h1 style="margin:6px 0 0; font-size:22px; font-weight:700; color:#ffffff;">{{ $vars['nama_sekolah'] ?? 'Sekolah' }}</h1>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 18px; font-size:15px; line-height:1.6; color:#334155;">
                                Assalamu'alaikum Bapak/Ibu,<br>
                                Berikut kami sampaikan informasi {{ $vars['aktivitas'] ?? 'kehadiran' }} putra/putri Anda.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f8fafc; font-size:13px; color:#64748b; width:38%;">Nama Siswa</td>
                                    <td style="padding:14px 18px; font-size:15px; font-weight:600; color:#0f172a;">{{ $vars['nama_siswa'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f8fafc; font-size:13px; color:#64748b; border-top:1px solid #e2e8f0;">Kelas</td>
                                    <td style="padding:14px 18px; font-size:15px; color:#0f172a; border-top:1px solid #e2e8f0;">{{ $vars['kelas'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f8fafc; font-size:13px; color:#64748b; border-top:1px solid #e2e8f0;">Keterangan</td>
                                    <td style="padding:14px 18px; border-top:1px solid #e2e8f0;">
                                        @php($pulang = ($vars['jenis'] ?? '') === 'Pulang')
                                        <span style="display:inline-block; padding:4px 12px; border-radius:9999px; background-color:{{ $pulang ? '#fef3c7' : '#dcfce7' }}; color:{{ $pulang ? '#92400e' : '#166534' }}; font-size:13px; font-weight:600;">{{ $pulang ? 'Pulang Sekolah' : 'Masuk Sekolah' }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f8fafc; font-size:13px; color:#64748b; border-top:1px solid #e2e8f0;">Status</td>
                                    <td style="padding:14px 18px; border-top:1px solid #e2e8f0;">
                                        <span style="display:inline-block; padding:4px 12px; border-radius:9999px; background-color:#dbeafe; color:#1e40af; font-size:13px; font-weight:600;">{{ $vars['status'] ?? '-' }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f8fafc; font-size:13px; color:#64748b; border-top:1px solid #e2e8f0;">Tanggal</td>
                                    <td style="padding:14px 18px; font-size:15px; color:#0f172a; border-top:1px solid #e2e8f0;">{{ $vars['tanggal'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f8fafc; font-size:13px; color:#64748b; border-top:1px solid #e2e8f0;">Waktu {{ $vars['jenis'] ?? '' }}</td>
                                    <td style="padding:14px 18px; font-size:15px; color:#0f172a; border-top:1px solid #e2e8f0;">{{ $vars['waktu'] ?? '-' }}</td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0; font-size:14px; line-height:1.6; color:#475569;">
                                Terima kasih atas perhatian dan kerja sama Bapak/Ibu dalam memantau kehadiran putra/putri.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px; background-color:#f8fafc; border-top:1px solid #e2e8f0;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#94a3b8;">
                                Email ini dikirim otomatis oleh sistem absensi {{ $vars['nama_sekolah'] ?? 'sekolah' }}.
                                Mohon tidak membalas email ini.
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin:16px 0 0; font-size:11px; color:#cbd5e1;">&copy; {{ date('Y') }} {{ $vars['nama_sekolah'] ?? 'Sekolah' }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
