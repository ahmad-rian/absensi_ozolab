# Implementation Plan: RFID Peserta

**Branch**: `008-rfid-peserta` (langsung di `main`) | **Date**: 2026-10-14 | **Spec**: [spec.md](./spec.md) | **Status**: direncanakan, bergantung perangkat

## Summary
Kolom `rfid_uid` (dinormalkan: heksadesimal huruf besar) per peserta, unik per penyelenggara (ditegakkan di
aplikasi — unique index dengan kolom nullable tidak menegakkan apa pun). `KehadiranRecorder::scan()` menerima
UID selain token QR bila saklar RFID kategori menyala; reader HID mengetik UID + Enter di halaman scan ringan.

## Constitution Check
I ✅ · II ✅ · III ✅ · IV ✅ kolom nullable pada tabel peserta, baris lama NULL · V ✅ RFID sekolah (rencana) tidak disentuh · VI ✅ · VII ✅

## Complexity Tracking
Tidak ada.
