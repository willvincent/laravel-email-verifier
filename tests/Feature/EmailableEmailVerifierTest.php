<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use WillVincent\EmailVerifier\External\EmailableEmailVerifier;

it('fails open when emailable not configured', function (): void {
    config()->set('email-verifier.external.emailable.api_key', '');
    config()->set('email-verifier.external.emailable.endpoint', '');

    $verifier = resolve(EmailableEmailVerifier::class);

    $r = $verifier->verify('foo@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->meta['provider'])->toBe('emailable')
        ->and($r->meta['configured'])->toBeFalse();
});

it('handles non-2xx as fail open', function (): void {
    config()->set('email-verifier.external.emailable.api_key', 'abc');
    config()->set('email-verifier.external.emailable.endpoint', 'https://api.emailable.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.emailable.com/*' => Http::response('', 500),
    ]);

    $verifier = resolve(EmailableEmailVerifier::class);

    $r = $verifier->verify('foo@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable');
});

it('accepts deliverable', function (): void {
    config()->set('email-verifier.external.emailable.api_key', 'abc');
    config()->set('email-verifier.external.emailable.endpoint', 'https://api.emailable.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.emailable.com/*' => Http::response(['state' => 'deliverable'], 200),
    ]);

    $verifier = resolve(EmailableEmailVerifier::class);

    $r = $verifier->verify('foo@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->reasons)->toBe([]);
});

it('handles undeliverable', function (): void {
    config()->set('email-verifier.external.emailable.api_key', 'abc');
    config()->set('email-verifier.external.emailable.endpoint', 'https://api.emailable.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.emailable.com/*' => Http::response(['state' => 'undeliverable'], 200),
    ]);

    $verifier = resolve(EmailableEmailVerifier::class);

    $r = $verifier->verify('foo@example.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(0)
        ->and($r->reasons)->toContain('external_rejected:undeliverable');
});

it('handles unknown', function (): void {
    config()->set('email-verifier.external.emailable.api_key', 'abc');
    config()->set('email-verifier.external.emailable.endpoint', 'https://api.emailable.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.emailable.com/*' => Http::response(['state' => 'unknown'], 200),
    ]);

    $verifier = resolve(EmailableEmailVerifier::class);

    $r = $verifier->verify('foo@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unknown');
});

it('handles risky', function (): void {
    config()->set('email-verifier.external.emailable.api_key', 'abc');
    config()->set('email-verifier.external.emailable.endpoint', 'https://api.emailable.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.emailable.com/*' => Http::response(['state' => 'risky'], 200),
    ]);

    $verifier = resolve(EmailableEmailVerifier::class);

    $r = $verifier->verify('foo@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(75)
        ->and($r->reasons)->toContain('external_risky');
});

it('fails open when state is empty', function (): void {
    config()->set('email-verifier.external.emailable.api_key', 'abc');
    config()->set('email-verifier.external.emailable.endpoint', 'https://api.emailable.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.emailable.com/*' => Http::response(['email' => 'foo@example.com'], 200),
    ]);

    $verifier = resolve(EmailableEmailVerifier::class);

    $r = $verifier->verify('foo@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable');
});

it('handles unrecognized status with default case', function (): void {
    config()->set('email-verifier.external.emailable.api_key', 'abc');
    config()->set('email-verifier.external.emailable.endpoint', 'https://api.emailable.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.emailable.com/*' => Http::response(['state' => 'new_state'], 200),
    ]);

    $verifier = resolve(EmailableEmailVerifier::class);

    $r = $verifier->verify('foo@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unrecognized_status');
});

it('handles exceptions gracefully', function (): void {
    config()->set('email-verifier.external.emailable.api_key', 'abc');
    config()->set('email-verifier.external.emailable.endpoint', 'https://api.emailable.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (): void {
        throw new RuntimeException('Connection failed');
    });

    $verifier = resolve(EmailableEmailVerifier::class);

    $r = $verifier->verify('foo@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_exception')
        ->and($r->meta['error'])->toBe('Connection failed');
});
