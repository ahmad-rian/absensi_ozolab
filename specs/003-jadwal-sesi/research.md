# Research: Jadwal & Sesi (003)

## R1. Pola → sesi konkret
- **Decision**: `sesi_polas` menyimpan aturan; `sesis` adalah kejadian per tanggal dengan SALINAN jendela waktu.
  `SesiGenerator::sinkron(Program)` membuat/memperbarui sesi mulai hari ini (zona program) dan tidak pernah
  menyentuh sesi yang sudah lewat (FR-008). Sesi hari ini yang sudah punya kehadiran hanya diubah bila
  admin mengonfirmasi (`termasuk_hari_ini`).
- **Rationale**: Penilaian kehadiran lama tetap memakai jadwal saat itu; sapuan alpa (004) punya baris nyata.
- **Alternatives**: Hitung sesi on-the-fly dari pola (ditolak: mengubah pola mengubah sejarah).

## R2. Tabrakan
- **Decision**: Dua sesi bertabrakan bila tanggal sama, rentang waktu (datang_mulai → pulang_selesai/datang_selesai)
  beririsan, DAN targetnya sama ATAU salah satunya seluruh program. Grup berbeda boleh bersamaan (US2-3).
  Pola mengikuti `PrayerSchedule::assertWindowsDoNotOverlap` (pesan menyebut sesi yang ditabrak).
- **Rationale**: Resolver scan harus deterministik: satu peserta, satu waktu, satu sesi.

## R3. Zona waktu
- **Decision**: Jam sesi = wall clock zona program (`programs.timezone`, default Asia/Jakarta). Resolver memakai
  `CarbonImmutable::now($program->timezone)`; tidak memakai `SchoolTime` (terkunci Jakarta).

## R4. Tabel `kehadirans` dibuat di 003
- **Decision**: Tabel + model minimal `Kehadiran` (unique sesi+peserta) dibuat di sini; pencatatan di 004.
- **Rationale**: FR-008/US5 butuh tahu sesi mana yang sudah punya kehadiran.

## R5. Batasan
- Lintas tengah malam ditolak; libur per program; granularitas menit.
