# Current Status — SewaBot MVP

## Updated
2026-08-15 — Member & OTP history moved to Dashboard

## Layout
- **Dashboard** (`/`): bot status, subscription, **Member & Saldo** (topup), **Riwayat OTP**
- **Konfigurasi Bot** (`/bots/{id}`): API key, markup, sync KOPKEN only

## OTP Saldo Flow
1. Hold saldo saat order nomor
2. OTP masuk → charge (potong)
3. Batal/expired → refund hold
Tidak pakai QRIS untuk OTP.

## OTP
- Base URL: admin global
- **API Key + Markup: per bot** di Konfigurasi Bot
- Markup: persen (%) atau flat (Rp)
- Harga jual = modal + markup

## Telegram commands
`/saldo` `/otp` `/status` `/ulang` `/ganti` `/batal`

## Env
```
OTP_API_BASE_URL=https://YOUR-API/v1
OTP_API_KEY=xxxxx
TELEGRAM_WEBHOOK_BASE_URL=https://bgsdtyo.net
```

## Poll
`php artisan otp:poll` (schedule every minute)
