<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\External;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;
use WillVincent\EmailVerifier\Contracts\ExternalEmailVerifier;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;

final readonly class NeverBounceEmailVerifier implements ExternalEmailVerifier
{
    public function __construct(
        private HttpFactory $http,
        private ConfigRepository $config,
    ) {}

    public function verify(string $email): EmailVerificationResult
    {
        /** @var string $apiKey */
        $apiKey = $this->config->get('email-verifier.external.neverbounce.api_key', '');
        /** @var string $endpoint */
        $endpoint = $this->config->get('email-verifier.external.neverbounce.endpoint', 'https://api.neverbounce.com/v4/single/check');
        /** @var int $timeout */
        $timeout = $this->config->get('email-verifier.external.timeout_seconds', 5);

        if ($apiKey === '' || $endpoint === '') {
            return new EmailVerificationResult(
                accepted: true,
                score: 100,
                normalizedEmail: $email,
                reasons: [],
                meta: ['provider' => 'neverbounce', 'configured' => false],
            );
        }

        try {
            $resp = $this->http
                ->timeout($timeout)
                ->retry(1, 250)
                ->get($endpoint, [
                    'key' => $apiKey,
                    'email' => $email,
                ]);

            if (! $resp->ok()) {
                return new EmailVerificationResult(
                    accepted: true,
                    score: 90,
                    normalizedEmail: $email,
                    reasons: ['external_provider_unavailable'],
                    meta: [
                        'provider' => 'neverbounce',
                        'configured' => true,
                        'http_status' => $resp->status(),
                    ],
                );
            }

            /** @var array<string, mixed> $data */
            $data = $resp->json() ?? [];

            // NeverBounce indicates status with "status" field at root level
            if (isset($data['status']) && $data['status'] !== 'success') {
                return new EmailVerificationResult(
                    accepted: true,
                    score: 90,
                    normalizedEmail: $email,
                    reasons: ['external_provider_unavailable'],
                    meta: [
                        'provider' => 'neverbounce',
                        'configured' => true,
                        'neverbounce_status' => $data['status'],
                        'raw' => $data,
                    ],
                );
            }

            // Result is in the "result" field
            /** @var string $resultRaw */
            $resultRaw = $data['result'] ?? '';
            $result = mb_strtolower($resultRaw);

            // If no result returned, treat as provider error
            if ($result === '') {
                return new EmailVerificationResult(
                    accepted: true,
                    score: 90,
                    normalizedEmail: $email,
                    reasons: ['external_provider_unavailable'],
                    meta: [
                        'provider' => 'neverbounce',
                        'configured' => true,
                        'raw' => $data,
                    ],
                );
            }

            $meta = [
                'provider' => 'neverbounce',
                'configured' => true,
                'result' => $result,
                'raw' => $data,
            ];

            // NeverBounce results: valid, invalid, disposable, catchall, unknown
            return match ($result) {
                'valid' => new EmailVerificationResult(
                    accepted: true,
                    score: 100,
                    normalizedEmail: $email,
                    reasons: [],
                    meta: $meta,
                ),

                'invalid', 'disposable' => new EmailVerificationResult(
                    accepted: false,
                    score: 0,
                    normalizedEmail: $email,
                    reasons: ['external_rejected:'.$result],
                    meta: $meta,
                ),

                'catchall' => new EmailVerificationResult(
                    accepted: true,
                    score: 85,
                    normalizedEmail: $email,
                    reasons: ['external_catch_all'],
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
                    'provider' => 'neverbounce',
                    'configured' => true,
                    'error' => $throwable->getMessage(),
                ],
            );
        }
    }
}
