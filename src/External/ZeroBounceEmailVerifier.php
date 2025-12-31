<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\External;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;
use WillVincent\EmailVerifier\Contracts\ExternalEmailVerifier;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;

final readonly class ZeroBounceEmailVerifier implements ExternalEmailVerifier
{
    public function __construct(
        private HttpFactory $http,
        private ConfigRepository $config,
    ) {}

    public function verify(string $email): EmailVerificationResult
    {
        /** @var string $apiKey */
        $apiKey = $this->config->get('email-verifier.external.zerobounce.api_key', '');
        /** @var string $endpoint */
        $endpoint = $this->config->get('email-verifier.external.zerobounce.endpoint', '');
        /** @var int $timeout */
        $timeout = $this->config->get('email-verifier.external.timeout_seconds', 5);

        if ($apiKey === '' || $endpoint === '') {
            // Provider not configured -> effectively "disabled" for this driver
            return new EmailVerificationResult(
                accepted: true,
                score: 100,
                normalizedEmail: $email,
                reasons: [],
                meta: ['provider' => 'zerobounce', 'configured' => false],
            );
        }

        try {
            $resp = $this->http
                ->timeout($timeout)
                ->retry(1, 250)
                ->get($endpoint, [
                    'api_key' => $apiKey,
                    'email' => $email,
                ]);

            if (! $resp->ok()) {
                return new EmailVerificationResult(
                    accepted: true, // fail open
                    score: 90,
                    normalizedEmail: $email,
                    reasons: ['external_provider_unavailable'],
                    meta: [
                        'provider' => 'zerobounce',
                        'configured' => true,
                        'http_status' => $resp->status(),
                    ],
                );
            }

            /** @var array<string, mixed> $data */
            $data = $resp->json() ?? [];

            // ZeroBounce indicates errors with an "error" field
            if (isset($data['error']) && $data['error'] !== '') {
                return new EmailVerificationResult(
                    accepted: true,
                    score: 90,
                    normalizedEmail: $email,
                    reasons: ['external_provider_unavailable'],
                    meta: [
                        'provider' => 'zerobounce',
                        'configured' => true,
                        'error' => $data['error'],
                        'raw' => $data,
                    ],
                );
            }

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
                        'provider' => 'zerobounce',
                        'configured' => true,
                        'raw' => $data,
                    ],
                );
            }

            // ZeroBounce statuses (examples): valid, invalid, catch-all, unknown, spamtrap, abuse, do_not_mail
            return match ($status) {
                'valid' => new EmailVerificationResult(
                    accepted: true,
                    score: 100,
                    normalizedEmail: $email,
                    reasons: [],
                    meta: ['provider' => 'zerobounce', 'configured' => true, 'status' => $status, 'raw' => $data],
                ),

                'catch-all' => new EmailVerificationResult(
                    accepted: true,
                    score: 85,
                    normalizedEmail: $email,
                    reasons: ['external_catch_all'],
                    meta: ['provider' => 'zerobounce', 'configured' => true, 'status' => $status, 'raw' => $data],
                ),

                'unknown' => new EmailVerificationResult(
                    accepted: true,
                    score: 80,
                    normalizedEmail: $email,
                    reasons: ['external_unknown'],
                    meta: ['provider' => 'zerobounce', 'configured' => true, 'status' => $status, 'raw' => $data],
                ),

                'invalid', 'spamtrap', 'abuse', 'do_not_mail' => new EmailVerificationResult(
                    accepted: false,
                    score: 0,
                    normalizedEmail: $email,
                    reasons: ['external_rejected:'.$status],
                    meta: ['provider' => 'zerobounce', 'configured' => true, 'status' => $status, 'raw' => $data],
                ),

                default => new EmailVerificationResult(
                    accepted: true,
                    score: 80,
                    normalizedEmail: $email,
                    reasons: ['external_unrecognized_status'],
                    meta: ['provider' => 'zerobounce', 'configured' => true, 'status' => $status, 'raw' => $data],
                ),
            };
        } catch (Throwable $throwable) {
            return new EmailVerificationResult(
                accepted: true, // fail open
                score: 90,
                normalizedEmail: $email,
                reasons: ['external_exception'],
                meta: [
                    'provider' => 'zerobounce',
                    'configured' => true,
                    'error' => $throwable->getMessage(),
                ],
            );
        }
    }
}
