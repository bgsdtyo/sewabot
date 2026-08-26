<?php

namespace App\Contracts;

use App\Models\TelegramBot;

interface OtpProviderInterface
{
    /**
     * Set the API key for the provider instance.
     */
    public function withApiKey(?string $apiKey): static;

    /**
     * Configure provider instance for a specific TelegramBot.
     */
    public function forBot(TelegramBot $bot): static;

    /**
     * Get list of available services from the provider.
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     price: int,
     *     stock: int,
     *     duration_seconds?: int
     * }>
     */
    public function getServices(int $timeout = 10): array;

    /**
     * Get account balance from the provider.
     *
     * @return array{balance: int|float, available?: int|float, reserved?: int|float, currency: string}
     */
    public function getBalance(): array;

    /**
     * Ping latency to provider server.
     *
     * @return array{ok: bool, ms: float, error: string|null}
     */
    public function pingLatency(): array;

    /**
     * Create a new OTP number rent order.
     *
     * @return array{
     *     id: string|int,
     *     token?: string|null,
     *     phone_number: string|null,
     *     status?: string,
     *     expire_at?: int|null,
     *     raw?: array
     * }
     */
    public function createOrder(int $serviceId, ?string $idempotencyKey = null): array;

    /**
     * Check / get status of an existing order.
     *
     * @return array{
     *     id: string|int,
     *     token?: string|null,
     *     phone_number?: string|null,
     *     status: string,
     *     otp_code?: string|null,
     *     full_text?: string|null,
     *     expire_at?: int|null,
     *     cancel_reason?: string|null,
     *     raw?: array
     * }
     */
    public function getOrder(string $providerOrderId, ?string $token = null): array;

    /**
     * Cancel an active order and release the number.
     */
    public function cancelOrder(string $providerOrderId, ?string $token = null): array;

    /**
     * Request OTP retry / resend on the same rented number.
     */
    public function resendOtp(string $providerOrderId, ?string $token = null): array;

    /**
     * Change number on the order.
     */
    public function changeNumber(string $providerOrderId, ?string $token = null, ?int $serviceId = null): array;

    /**
     * Acknowledge / complete order on provider server.
     */
    public function doneOrder(string $providerOrderId, ?string $token = null): array;
}
