# Data Model: 002

## Tabel baru (ULID, semua ber-`penyelenggara_id` + BelongsToPenyelenggara)
| Tabel | Kolom utama | Catatan |
|---|---|---|
| kategoris | name, slug (unik per penyelenggara), template, istilah_peserta, istilah_grup, features json, card_dataset_id, archived_at | skema kolom di card_datasets.fields |
| programs | kategori_id, name, starts_on, ends_on, timezone (default Asia/Jakarta), archived_at, card_form_id | arsip = baca-saja |
| grups | program_id, parent_id nullable, name, urutan | hapus ditolak bila punya anak/peserta |
| grup_peserta | grup_id, card_form_submission_id | unique pasangan |
| grup_peserta_riwayats | card_form_submission_id, dari_grup_id, ke_grup_id, aksi (masuk/pindah/keluar), oleh, created_at | FR-009 |
| grup_pendamping | grup_id, user_id | unique pasangan |
| peserta_imports | program_id, filename, status, total, berhasil, gagal, errors json, oleh | laporan baris gagal |

## Kolom baru di tabel lama (nullable, baris lama tetap NULL)
| Tabel | Kolom |
|---|---|
| card_datasets | penyelenggara_id, kategori_id |
| card_forms | penyelenggara_id, program_id |
| card_form_submissions | penyelenggara_id, program_id, archived_at |

## Format `fields` (sama dengan Kartu Bebas)
`[{key, label, type: text|number|date|select|photo, required, options?: [], hidden?: bool, core?: bool}]`
Kolom inti: `nama` (text, required, core), `telepon_wali` (text, core).

## Setting global
`drive_penyelenggara_root_folder_id` — ID folder "Tyas General Apps".
