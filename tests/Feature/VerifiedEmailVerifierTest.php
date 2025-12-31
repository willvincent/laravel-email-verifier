<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WillVincent\EmailVerifier\External\VerifiedEmailVerifier;

it('fails open when verifiedemail is not configured', function (): void {
    config()->set('email-verifier.external.verifiedemail.api_key', '');
    config()->set('email-verifier.external.verifiedemail.endpoint', '');

    $verifier = resolve(VerifiedEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->meta['provider'])->toBe('verifiedemail')
        ->and($r->meta['configured'])->toBeFalse();
});

it('fails open when verifiedemail returns non-2xx', function (): void {
    config()->set('email-verifier.external.verifiedemail.api_key', 'abc');
    config()->set('email-verifier.external.verifiedemail.endpoint', 'https://app.verify-email.org/api/v1/');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'app.verify-email.org/*' => Http::response('', 503),
    ]);

    $verifier = resolve(VerifiedEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['provider'])->toBe('verifiedemail')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['http_status'])->toBe(503);
});

it('accepts verified (status_code=1) with score 100', function (): void {
    config()->set('email-verifier.external.verifiedemail.api_key', 'abc');
    config()->set('email-verifier.external.verifiedemail.endpoint', 'https://app.verify-email.org/api/v1/');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (Request $request) {
        expect($request->url())->toContain('app.verify-email.org/api/v1/abc');
        expect($request['email'])->toBe('person@example.com');

        return Http::response([
            'status_code' => 1,
            'status' => 'ok',
            'email' => 'person@example.com',
        ], 200);
    });

    $verifier = resolve(VerifiedEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->reasons)->toBe([])
        ->and($r->meta['provider'])->toBe('verifiedemail')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status_code'])->toBe(1);
});

it('rejects unverifiable (status_code=0)', function (): void {
    config()->set('email-verifier.external.verifiedemail.api_key', 'abc');
    config()->set('email-verifier.external.verifiedemail.endpoint', 'https://app.verify-email.org/api/v1/');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'app.verify-email.org/*' => Http::response([
            'status_code' => 0,
            'status' => 'error',
            'email' => 'invalid@example.com',
            'error' => 'Email does not exist',
        ], 200),
    ]);

    $verifier = resolve(VerifiedEmailVerifier::class);

    $r = $verifier->verify('invalid@example.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(0)
        ->and($r->reasons)->toContain('external_rejected:unverifiable')
        ->and($r->meta['provider'])->toBe('verifiedemail')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status_code'])->toBe(0);
});

it('fails open when status_code is missing', function (): void {
    config()->set('email-verifier.external.verifiedemail.api_key', 'abc');
    config()->set('email-verifier.external.verifiedemail.endpoint', 'https://app.verify-email.org/api/v1/');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'app.verify-email.org/*' => Http::response([
            'email' => 'person@example.com',
        ], 200),
    ]);

    $verifier = resolve(VerifiedEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['provider'])->toBe('verifiedemail')
        ->and($r->meta['configured'])->toBeTrue();
});

it('handles exceptions gracefully', function (): void {
    config()->set('email-verifier.external.verifiedemail.api_key', 'abc');
    config()->set('email-verifier.external.verifiedemail.endpoint', 'https://app.verify-email.org/api/v1/');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'app.verify-email.org/*' => function (): void {
            throw new Exception('Network error');
        },
    ]);

    $verifier = resolve(VerifiedEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_exception')
        ->and($r->meta['provider'])->toBe('verifiedemail')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['error'])->toBe('Network error');
});
