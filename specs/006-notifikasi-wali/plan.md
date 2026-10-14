# Implementation Plan: Notifikasi Wali

**Branch**: `006-notifikasi-wali` (langsung di `main`) | **Date**: 2026-10-14 | **Spec**: [spec.md](./spec.md) | **Status**: direncanakan, belum dikerjakan

## Summary
Event `KehadiranTercatat` (dari `KehadiranRecorder` & sapuan alpa) → job antrean `notif-penyelenggara`
(terpisah dari `default`/`whatsapp` sekolah) → `NotificationDispatcher` memakai gateway WA/Email/Telegram yang
ada. Kuota harian per penyelenggara (`penyelenggaras.wa_daily_quota`) dihitung dari log notifikasi
penyelenggara sendiri, tidak memakai `WhatsAppQuota` sekolah.

## Technical Context
Reuse: `App\Services\Notification\NotificationDispatcher`, `DefaultWhatsAppGateway`, `MessageTemplateRenderer`.
Baru: tabel `penyelenggara_notification_logs`, template per penyelenggara di `penyelenggaras.settings`.

## Constitution Check
I ✅ · II ✅ log ber-tenant · III ✅ · IV ✅ · V ✅ antrean & kuota terpisah dari sekolah (Gerbang uji beban) · VI ✅ · VII ✅

## Complexity Tracking
Tidak ada.
