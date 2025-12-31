<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WillVincent\EmailVerifier\External\QuickemailverificationEmailVerifier;

it('fails open when quickemailverification is not configured', function (): void {
    config()->set('email-verifier.external.quickemailverification.api_key', '');
    config()->set('email-verifier.external.quickemailverification.endpoint', '');

    $verifier = resolve(QuickemailverificationEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->meta['provider'])->toBe('quickemailverification')
        ->and($r->meta['configured'])->toBeFalse();
});

it('fails open when quickemailverification returns non-2xx', function (): void {
    config()->set('email-verifier.external.quickemailverification.api_key', 'abc');
    config()->set('email-verifier.external.quickemailverification.endpoint', 'https://api.quickemailverification.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.quickemailverification.com/*' => Http::response('', 503),
    ]);

    $verifier = resolve(QuickemailverificationEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['provider'])->toBe('quickemailverification')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['http_status'])->toBe(503);
});

it('accepts valid with score 100', function (): void {
    config()->set('email-verifier.external.quickemailverification.api_key', 'abc');
    config()->set('email-verifier.external.quickemailverification.endpoint', 'https://api.quickemailverification.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (Request $request) {
        expect($request->url())->toContain('api.quickemailverification.com/v1/verify');
        expect($request['apikey'])->toBe('abc');
        expect($request['email'])->toBe('person@example.com');

        return Http::response([
            'success' => true,
            'result' => 'valid',
            'reason' => 'accepted_email',
            'disposable' => false,
            'accept_all' => false,
            'role' => false,
            'free' => false,
            'email' => 'person@example.com',
            'user' => 'person',
            'domain' => 'example.com',
            'safe_to_send' => true,
            'did_you_mean' => '',
        ], 200);
    });

    $verifier = resolve(QuickemailverificationEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->reasons)->toBe([])
        ->and($r->meta['provider'])->toBe('quickemailverification')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('valid');
});

it('rejects invalid', function (): void {
    config()->set('email-verifier.external.quickemailverification.api_key', 'abc');
    config()->set('email-verifier.external.quickemailverification.endpoint', 'https://api.quickemailverification.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.quickemailverification.com/*' => Http::response([
            'success' => true,
            'result' => 'invalid',
            'reason' => 'rejected_email',
            'disposable' => false,
            'accept_all' => false,
            'role' => false,
            'free' => false,
            'email' => 'person@gamil.com',
            'user' => 'person',
            'domain' => 'gamil.com',
            'safe_to_send' => false,
            'did_you_mean' => 'person@gmail.com',
        ], 200),
    ]);

    $verifier = resolve(QuickemailverificationEmailVerifier::class);

    $r = $verifier->verify('person@gamil.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(0)
        ->and($r->reasons)->toContain('external_rejected:rejected_email')
        ->and($r->meta['provider'])->toBe('quickemailverification')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('invalid')
        ->and($r->meta['reason'])->toBe('rejected_email');
});

it('treats unknown as accepted but low confidence', function (): void {
    config()->set('email-verifier.external.quickemailverification.api_key', 'abc');
    config()->set('email-verifier.external.quickemailverification.endpoint', 'https://api.quickemailverification.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.quickemailverification.com/*' => Http::response([
            'success' => true,
            'result' => 'unknown',
            'reason' => 'timeout',
            'disposable' => false,
            'accept_all' => false,
            'role' => false,
            'free' => false,
            'email' => 'person@example.com',
            'user' => 'person',
            'domain' => 'example.com',
            'safe_to_send' => true,
        ], 200),
    ]);

    $verifier = resolve(QuickemailverificationEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unknown')
        ->and($r->meta['provider'])->toBe('quickemailverification')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('unknown');
});

it('fails open when success is false', function (): void {
    config()->set('email-verifier.external.quickemailverification.api_key', 'abc');
    config()->set('email-verifier.external.quickemailverification.endpoint', 'https://api.quickemailverification.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.quickemailverification.com/*' => Http::response([
            'success' => false,
            'message' => 'Invalid API key',
        ], 200),
    ]);

    $verifier = resolve(QuickemailverificationEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['quickemailverification_success'])->toBeFalse();
});

it('handles unrecognized result with default case', function (): void {
    config()->set('email-verifier.external.quickemailverification.api_key', 'abc');
    config()->set('email-verifier.external.quickemailverification.endpoint', 'https://api.quickemailverification.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.quickemailverification.com/*' => Http::response([
            'success' => true,
            'result' => 'future_status',
            'email' => 'person@example.com',
        ], 200),
    ]);

    $verifier = resolve(QuickemailverificationEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unrecognized_status');
});

it('handles exceptions gracefully', function (): void {
    config()->set('email-verifier.external.quickemailverification.api_key', 'abc');
    config()->set('email-verifier.external.quickemailverification.endpoint', 'https://api.quickemailverification.com/v1/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (): void {
        throw new RuntimeException('Connection timeout');
    });

    $verifier = resolve(QuickemailverificationEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_exception')
        ->and($r->meta['error'])->toBe('Connection timeout');
});
