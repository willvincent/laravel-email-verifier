<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\External;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;
use WillVincent\EmailVerifier\Contracts\ExternalEmailVerifier;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;

final readonly class BouncerEmailVerifier implements ExternalEmailVerifier
{
    public function __construct(
        private HttpFactory $http,
        private ConfigRepository $config,
    ) {}

    public function verify(string $email): EmailVerificationResult
    {
        /** @var string $apiKey */
        $apiKey = $this->config->get('email-verifier.external.bouncer.api_key', '');
        /** @var string $endpoint */
        $endpoint = $this->config->get('email-verifier.external.bouncer.endpoint', 'https://api.usebouncer.com/v1.1/email/verify');
        /** @var int $timeout */
        $timeout = $this->config->get('email-verifier.external.timeout_seconds', 5);

        if ($apiKey === '' || $endpoint === '') {
            return new EmailVerificationResult(
                accepted: true,
                score: 100,
                normalizedEmail: $email,
                reasons: [],
                meta: ['provider' => 'bouncer', 'configured' => false],
            );
        }

        try {
            $resp = $this->http
                ->timeout($timeout)
                ->retry(1, 250)
                ->get($endpoint, [
                    'email' => $email,
                    'api_key' => $apiKey,
                ]);

            if (! $resp->ok()) {
                return new EmailVerificationResult(
                    accepted: true,
                    score: 90,
                    normalizedEmail: $email,
                    reasons: ['external_provider_unavailable'],
                    meta: [
                        'provider' => 'bouncer',
                        'configured' => true,
                        'http_status' => $resp->status(),
                    ],
                );
            }

            /** @var array<string, mixed> $data */
            $data = $resp->json() ?? [];

            // Bouncer returns a "status" field to indicate result
            /** @var string $statusRaw */
            $statusRaw = $data['status'] ?? '';
            $status = mb_strtolower($statusRaw);

            // If no status returned, treat as provider error
            if ($status === '') {
                return new EmailVerificationResult(
                    accepted: true,
                    score: 90,
                    normalizedEmail: $email,
                    reasons: ['external_provider_unavailable'],
                    meta: [
                        'provider' => 'bouncer',
                        'configured' => true,
                        'raw' => $data,
                    ],
                );
            }

            $meta = [
                'provider' => 'bouncer',
                'configured' => true,
                'status' => $status,
                'raw' => $data,
            ];

            // Bouncer statuses: deliverable, undeliverable, risky, unknown
            return match ($status) {
                'deliverable' => new EmailVerificationResult(
                    accepted: true,
                    score: 100,
                    normalizedEmail: $email,
                    reasons: [],
                    meta: $meta,
                ),

                'undeliverable' => new EmailVerificationResult(
                    accepted: false,
                    score: 0,
                    normalizedEmail: $email,
                    reasons: ['external_rejected:undeliverable'],
                    meta: $meta,
                ),

                'risky' => new EmailVerificationResult(
                    accepted: true,
                    score: 75,
                    normalizedEmail: $email,
                    reasons: ['external_risky'],
                    meta: $meta,
                ),

                'unknown' => new EmailVerificationResult(
                    accepted: true,
                    score: 80,
                    normalizedEmail: $email,
                    reasons: ['external_unknown'],
                    meta: $meta,
                ),

                default => new EmailVerificationResult(
                    accepted: true,
                    score: 80,
                    normalizedEmail: $email,
                    reasons: ['external_unrecognized_status'],
                    meta: $meta,
                ),
            };
        } catch (Throwable $throwable) {
            return new EmailVerificationResult(
                accepted: true,
                score: 90,
                normalizedEmail: $email,
                reasons: ['external_exception'],
                meta: [
                    'provider' => 'bouncer',
                    'configured' => true,
                    'error' => $throwable->getMessage(),
                ],
            );
        }
    }
}
