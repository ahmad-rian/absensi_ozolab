# Implementation Plan: Portal Wali

**Branch**: `007-portal-wali` (langsung di `main`) | **Date**: 2026-10-14 | **Spec**: [spec.md](./spec.md) | **Status**: direncanakan, belum dikerjakan

## Summary
Peran baru `WALI_PESERTA` (terpisah dari `ORANG_TUA` sekolah). Akun wali tertaut ke peserta lewat tabel
`wali_peserta` berdasarkan kolom inti `telepon_wali` (cocok persis). Area `/wali` dengan layout sendiri,
riwayat kehadiran dari `RingkasanKehadiran`. Kartu digital publik lewat token tak tertebak yang bisa dicabut.

## Technical Context
Reuse pola `OrangTua/PortalController`, `EnsureAnakSendiri`, kebijakan sandi orang tua (8 karakter + daftar tolak + throttle).

## Constitution Check
I ✅ · II ✅ wali hanya melihat peserta tertaut; 404 seragam · III ✅ · IV ✅ · V ✅ portal orang tua sekolah tidak disentuh · VI ✅ · VII ✅

## Complexity Tracking
Tidak ada.
