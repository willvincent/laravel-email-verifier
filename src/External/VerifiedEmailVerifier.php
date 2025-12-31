<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\External;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;
use WillVincent\EmailVerifier\Contracts\ExternalEmailVerifier;
use WillVincent\EmailVerifier\Results\EmailVerificationResult;

final readonly class VerifiedEmailVerifier implements ExternalEmailVerifier
{
    public function __construct(
        private HttpFactory $http,
        private ConfigRepository $config,
    ) {}

    public function verify(string $email): EmailVerificationResult
    {
        /** @var string $apiKey */
        $apiKey = $this->config->get('email-verifier.external.verifiedemail.api_key', '');
        /** @var string $endpoint */
        $endpoint = $this->config->get('email-verifier.external.verifiedemail.endpoint', 'https://app.verify-email.org/api/v1/');
        /** @var int $timeout */
        $timeout = $this->config->get('email-verifier.external.timeout_seconds', 5);

        if ($apiKey === '' || $endpoint === '') {
            return new EmailVerificationResult(
                accepted: true,
                score: 100,
                normalizedEmail: $email,
                reasons: [],
                meta: ['provider' => 'verifiedemail', 'configured' => false],
            );
        }

        try {
            // Build the full endpoint URL with API key
            $url = mb_rtrim($endpoint, '/').'/'.$apiKey;

            $resp = $this->http
                ->timeout($timeout)
                ->retry(1, 250)
                ->get($url, [
                    'email' => $email,
                ]);

            if (! $resp->ok()) {
                return new EmailVerificationResult(
                    accepted: true,
                    score: 90,
                    normalizedEmail: $email,
                    reasons: ['external_provider_unavailable'],
                    meta: [
                        'provider' => 'verifiedemail',
                        'configured' => true,
                        'http_status' => $resp->status(),
                    ],
                );
            }

            /** @var array<string, mixed> $data */
            $data = $resp->json() ?? [];

            // VerifiedEmail returns status code: 0 = unverifiable, 1 = verified
            /** @var int $statusCode */
            $statusCode = $data['status_code'] ?? -1;

            // If no valid status code, treat as provider error
            if ($statusCode === -1) {
                return new EmailVerificationResult(
                    accepted: true,
                    score: 90,
                    normalizedEmail: $email,
                    reasons: ['external_provider_unavailable'],
                    meta: [
                        'provider' => 'verifiedemail',
                        'configured' => true,
                        'raw' => $data,
                    ],
                );
            }

            $meta = [
                'provider' => 'verifiedemail',
                'configured' => true,
                'status_code' => $statusCode,
                'raw' => $data,
            ];

            // Status code 1 means verified/deliverable
            return match ($statusCode) {
                1 => new EmailVerificationResult(
                    accepted: true,
                    score: 100,
                    normalizedEmail: $email,
                    reasons: [],
                    meta: $meta,
                ),

                0 => new EmailVerificationResult(
                    accepted: false,
                    score: 0,
                    normalizedEmail: $email,
                    reasons: ['external_rejected:unverifiable'],
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
                    'provider' => 'verifiedemail',
                    'configured' => true,
                    'error' => $throwable->getMessage(),
                ],
            );
        }
    }
}
