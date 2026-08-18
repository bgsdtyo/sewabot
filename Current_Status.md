# Current Status — SewaBot MVP

## Updated
2026-08-18 — Fix bulk OTP: watcher CLI detached (bukan FPM afterResponse). Cron HTTP `/cron/otp-poll` + Cek OTP di bubble.
2026-08-18 — Fix bulk OTP: watcher in-process (tanpa HTTP ke diri sendiri; deadlock FPM). Poll semua dulu, baru edit bubble.
2026-08-18 — Fix bulk OTP #3: watcher per-order (HTTP terpisah), jangan nunggu #1/#2
2026-08-18 — Fix bulk OTP: jangan timpa OTP MASUK; complete saat OTP ada
2026-08-18 — Fix bulk OTP: edit bubble #2/#3 saat OTP masuk (per-order message_id, no fan-out)
2026-08-18 — `/otp-orders`: kolom member tampil ID mentah, PID layanan disingkat
2026-08-18 — `/otp-orders`: modal detail (icon mata) rapih & responsive mobile
2026-08-18 — `/otp-orders`: hapus filter layanan, khusus KOPKEN saja
2026-08-18 — `/otp-orders`: filter member bisa dicari (username / ID)
2026-08-18 — `/otp-orders`: toggle filter mobile pakai CSS+JS native (tanpa Tailwind JIT)
2026-08-18 — `/otp-orders`: filter mobile bisa buka/tutup, default tertutup
2026-08-18 — `/otp-orders`: swipe kiri-kanan via inline CSS + touch handler (tanpa andalkan Vite build)
2026-08-18 — `/otp-orders`: tabel bisa digeser kiri-kanan di mobile
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
