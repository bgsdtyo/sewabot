# Current Status — SewaBot MVP

## Updated
2026-08-15 — Fix dashboard: tampilkan bot dari user_id + sync subscription saat assign admin
2026-08-15 — Admin dashboard: stats bot aktif, chart OTP/revenue, tabel performa bot
2026-08-15 — Fix 403 checkout pembayaran (cast user_id + auth compare)
2026-08-15 — Admin `/admin/orders`: hapus order (single + bulk)
2026-08-15 — Auto-watch OTP tiap 2s setelah order (tanpa klik Cek OTP / tanpa andalkan cron)
2026-08-15 — OTP masuk otomatis edit bubble (tanpa klik Cek OTP); poll 30s; cache message_id
2026-08-15 — Cek OTP: edit bubble only (no spam fallback); OTP masuk edit bubble
2026-08-15 — OTP masuk: edit bubble order (status SELESAI), simpan telegram_message_id
2026-08-15 — Ya/Batal konfirmasi OTP: edit bubble (bukan kirim baru)
2026-08-15 — Rapihkan teks cancel bot (newline + wording)
2026-08-15 — QRIS dinamis (verssache/qris-dinamis port)

## Payment QRIS
- Admin `/admin/payment-settings`: paste **QRIS Static String**
- Checkout generate **QRIS dinamis** per nominal invoice (`/checkout/order/{id}/qris.png`)
- Logic port dari https://github.com/verssache/qris-dinamis (TLV + CRC16)
- Package QR image: `endroid/qr-code`

## Layout
- **Dashboard**: bot, subscription, Member & Saldo, Riwayat OTP
- **Konfigurasi Bot**: API key, markup, sync KOPKEN

## OTP
Hold → charge saat OTP → refund cancel/expire. Commands: `/saldo` `/otp` `/status` `/ulang` `/ganti` `/batal`

## Poll
`php artisan otp:poll` via `schedule:run` tiap menit

## Hosting
Repo: https://github.com/bgsdtyo/sewabot  
Path: `/home/bgsdtyon/domains/bgsdtyo.net/sewabot`  
`public_html` → `sewabot/public`
