<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WillVincent\EmailVerifier\External\BouncerEmailVerifier;

it('fails open when bouncer is not configured', function (): void {
    config()->set('email-verifier.external.bouncer.api_key', '');
    config()->set('email-verifier.external.bouncer.endpoint', '');

    $verifier = resolve(BouncerEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->meta['provider'])->toBe('bouncer')
        ->and($r->meta['configured'])->toBeFalse();
});

it('fails open when bouncer returns non-2xx', function (): void {
    config()->set('email-verifier.external.bouncer.api_key', 'abc');
    config()->set('email-verifier.external.bouncer.endpoint', 'https://api.usebouncer.com/v1.1/email/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.usebouncer.com/*' => Http::response('', 503),
    ]);

    $verifier = resolve(BouncerEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['provider'])->toBe('bouncer')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['http_status'])->toBe(503);
});

it('accepts deliverable with score 100', function (): void {
    config()->set('email-verifier.external.bouncer.api_key', 'abc');
    config()->set('email-verifier.external.bouncer.endpoint', 'https://api.usebouncer.com/v1.1/email/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (Request $request) {
        expect($request->url())->toContain('api.usebouncer.com/v1.1/email/verify');
        expect($request['api_key'])->toBe('abc');
        expect($request['email'])->toBe('person@example.com');

        return Http::response([
            'status' => 'deliverable',
            'reason' => 'accepted',
            'domain' => 'example.com',
            'account' => 'person',
            'email' => 'person@example.com',
        ], 200);
    });

    $verifier = resolve(BouncerEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->reasons)->toBe([])
        ->and($r->meta['provider'])->toBe('bouncer')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('deliverable');
});

it('rejects undeliverable', function (): void {
    config()->set('email-verifier.external.bouncer.api_key', 'abc');
    config()->set('email-verifier.external.bouncer.endpoint', 'https://api.usebouncer.com/v1.1/email/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.usebouncer.com/*' => Http::response([
            'status' => 'undeliverable',
            'reason' => 'mailbox_not_found',
            'domain' => 'example.com',
            'account' => 'invalid',
            'email' => 'invalid@example.com',
        ], 200),
    ]);

    $verifier = resolve(BouncerEmailVerifier::class);

    $r = $verifier->verify('invalid@example.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(0)
        ->and($r->reasons)->toContain('external_rejected:undeliverable')
        ->and($r->meta['provider'])->toBe('bouncer')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('undeliverable');
});

it('treats risky as accepted but penalized', function (): void {
    config()->set('email-verifier.external.bouncer.api_key', 'abc');
    config()->set('email-verifier.external.bouncer.endpoint', 'https://api.usebouncer.com/v1.1/email/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.usebouncer.com/*' => Http::response([
            'status' => 'risky',
            'reason' => 'low_deliverability',
            'domain' => 'example.com',
            'account' => 'support',
            'email' => 'support@example.com',
        ], 200),
    ]);

    $verifier = resolve(BouncerEmailVerifier::class);

    $r = $verifier->verify('support@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(75)
        ->and($r->reasons)->toContain('external_risky')
        ->and($r->meta['provider'])->toBe('bouncer')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('risky');
});

it('treats unknown as accepted but low confidence', function (): void {
    config()->set('email-verifier.external.bouncer.api_key', 'abc');
    config()->set('email-verifier.external.bouncer.endpoint', 'https://api.usebouncer.com/v1.1/email/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.usebouncer.com/*' => Http::response([
            'status' => 'unknown',
            'reason' => 'timeout',
            'domain' => 'example.com',
            'account' => 'person',
            'email' => 'person@example.com',
        ], 200),
    ]);

    $verifier = resolve(BouncerEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unknown')
        ->and($r->meta['provider'])->toBe('bouncer')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('unknown');
});

it('fails open when status is empty', function (): void {
    config()->set('email-verifier.external.bouncer.api_key', 'abc');
    config()->set('email-verifier.external.bouncer.endpoint', 'https://api.usebouncer.com/v1.1/email/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.usebouncer.com/*' => Http::response([
            'email' => 'person@example.com',
        ], 200),
    ]);

    $verifier = resolve(BouncerEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['provider'])->toBe('bouncer')
        ->and($r->meta['configured'])->toBeTrue();
});

it('handles unrecognized status with default case', function (): void {
    config()->set('email-verifier.external.bouncer.api_key', 'abc');
    config()->set('email-verifier.external.bouncer.endpoint', 'https://api.usebouncer.com/v1.1/email/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.usebouncer.com/*' => Http::response([
            'status' => 'some_new_status_we_dont_know',
            'email' => 'person@example.com',
        ], 200),
    ]);

    $verifier = resolve(BouncerEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unrecognized_status')
        ->and($r->meta['provider'])->toBe('bouncer')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('some_new_status_we_dont_know');
});

it('handles exceptions gracefully', function (): void {
    config()->set('email-verifier.external.bouncer.api_key', 'abc');
    config()->set('email-verifier.external.bouncer.endpoint', 'https://api.usebouncer.com/v1.1/email/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (): void {
        throw new RuntimeException('Network timeout');
    });

    $verifier = resolve(BouncerEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_exception')
        ->and($r->meta['provider'])->toBe('bouncer')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['error'])->toBe('Network timeout');
});
