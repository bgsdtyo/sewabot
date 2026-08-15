# Current Status — SewaBot MVP

## Updated
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
