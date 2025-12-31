<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WillVincent\EmailVerifier\External\NeverBounceEmailVerifier;

it('fails open when neverbounce is not configured', function (): void {
    config()->set('email-verifier.external.neverbounce.api_key', '');
    config()->set('email-verifier.external.neverbounce.endpoint', '');

    $verifier = resolve(NeverBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->meta['provider'])->toBe('neverbounce')
        ->and($r->meta['configured'])->toBeFalse();
});

it('fails open when neverbounce returns non-2xx', function (): void {
    config()->set('email-verifier.external.neverbounce.api_key', 'abc');
    config()->set('email-verifier.external.neverbounce.endpoint', 'https://api.neverbounce.com/v4/single/check');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.neverbounce.com/*' => Http::response('', 503),
    ]);

    $verifier = resolve(NeverBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['provider'])->toBe('neverbounce')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['http_status'])->toBe(503);
});

it('accepts valid with score 100', function (): void {
    config()->set('email-verifier.external.neverbounce.api_key', 'abc');
    config()->set('email-verifier.external.neverbounce.endpoint', 'https://api.neverbounce.com/v4/single/check');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (Request $request) {
        expect($request->url())->toContain('api.neverbounce.com/v4/single/check');
        expect($request['key'])->toBe('abc');
        expect($request['email'])->toBe('person@example.com');

        return Http::response([
            'status' => 'success',
            'result' => 'valid',
            'flags' => [],
            'suggested_correction' => '',
            'execution_time' => 123,
        ], 200);
    });

    $verifier = resolve(NeverBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->reasons)->toBe([])
        ->and($r->meta['provider'])->toBe('neverbounce')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['result'])->toBe('valid');
});

it('rejects invalid', function (): void {
    config()->set('email-verifier.external.neverbounce.api_key', 'abc');
    config()->set('email-verifier.external.neverbounce.endpoint', 'https://api.neverbounce.com/v4/single/check');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.neverbounce.com/*' => Http::response([
            'status' => 'success',
            'result' => 'invalid',
            'flags' => ['has_dns', 'has_dns_mx'],
            'suggested_correction' => '',
            'execution_time' => 95,
        ], 200),
    ]);

    $verifier = resolve(NeverBounceEmailVerifier::class);

    $r = $verifier->verify('invalid@example.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(0)
        ->and($r->reasons)->toContain('external_rejected:invalid')
        ->and($r->meta['provider'])->toBe('neverbounce')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['result'])->toBe('invalid');
});

it('rejects disposable', function (): void {
    config()->set('email-verifier.external.neverbounce.api_key', 'abc');
    config()->set('email-verifier.external.neverbounce.endpoint', 'https://api.neverbounce.com/v4/single/check');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.neverbounce.com/*' => Http::response([
            'status' => 'success',
            'result' => 'disposable',
            'flags' => ['disposable_email'],
            'suggested_correction' => '',
            'execution_time' => 110,
        ], 200),
    ]);

    $verifier = resolve(NeverBounceEmailVerifier::class);

    $r = $verifier->verify('temp@tempmail.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(0)
        ->and($r->reasons)->toContain('external_rejected:disposable')
        ->and($r->meta['provider'])->toBe('neverbounce')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['result'])->toBe('disposable');
});

it('treats catchall as accepted but penalized', function (): void {
    config()->set('email-verifier.external.neverbounce.api_key', 'abc');
    config()->set('email-verifier.external.neverbounce.endpoint', 'https://api.neverbounce.com/v4/single/check');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.neverbounce.com/*' => Http::response([
            'status' => 'success',
            'result' => 'catchall',
            'flags' => ['smtp_connectable'],
            'suggested_correction' => '',
            'execution_time' => 134,
        ], 200),
    ]);

    $verifier = resolve(NeverBounceEmailVerifier::class);

    $r = $verifier->verify('anything@catchall.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(85)
        ->and($r->reasons)->toContain('external_catch_all')
        ->and($r->meta['provider'])->toBe('neverbounce')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['result'])->toBe('catchall');
});

it('treats unknown as accepted but low confidence', function (): void {
    config()->set('email-verifier.external.neverbounce.api_key', 'abc');
    config()->set('email-verifier.external.neverbounce.endpoint', 'https://api.neverbounce.com/v4/single/check');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.neverbounce.com/*' => Http::response([
            'status' => 'success',
            'result' => 'unknown',
            'flags' => [],
            'suggested_correction' => '',
            'execution_time' => 200,
        ], 200),
    ]);

    $verifier = resolve(NeverBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unknown')
        ->and($r->meta['provider'])->toBe('neverbounce')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['result'])->toBe('unknown');
});

it('fails open when status is not success', function (): void {
    config()->set('email-verifier.external.neverbounce.api_key', 'abc');
    config()->set('email-verifier.external.neverbounce.endpoint', 'https://api.neverbounce.com/v4/single/check');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.neverbounce.com/*' => Http::response([
            'status' => 'auth_failure',
            'message' => 'Invalid API key',
        ], 200),
    ]);

    $verifier = resolve(NeverBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['provider'])->toBe('neverbounce')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['neverbounce_status'])->toBe('auth_failure');
});

it('fails open when result is empty', function (): void {
    config()->set('email-verifier.external.neverbounce.api_key', 'abc');
    config()->set('email-verifier.external.neverbounce.endpoint', 'https://api.neverbounce.com/v4/single/check');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.neverbounce.com/*' => Http::response([
            'status' => 'success',
        ], 200),
    ]);

    $verifier = resolve(NeverBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['provider'])->toBe('neverbounce')
        ->and($r->meta['configured'])->toBeTrue();
});

it('handles unrecognized result with default case', function (): void {
    config()->set('email-verifier.external.neverbounce.api_key', 'abc');
    config()->set('email-verifier.external.neverbounce.endpoint', 'https://api.neverbounce.com/v4/single/check');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.neverbounce.com/*' => Http::response([
            'status' => 'success',
            'result' => 'future_result',
        ], 200),
    ]);

    $verifier = resolve(NeverBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unrecognized_status');
});

it('handles exceptions gracefully', function (): void {
    config()->set('email-verifier.external.neverbounce.api_key', 'abc');
    config()->set('email-verifier.external.neverbounce.endpoint', 'https://api.neverbounce.com/v4/single/check');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (): void {
        throw new RuntimeException('API timeout');
    });

    $verifier = resolve(NeverBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_exception')
        ->and($r->meta['error'])->toBe('API timeout');
});
