<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WillVincent\EmailVerifier\External\KickboxEmailVerifier;

it('fails open when kickbox is not configured', function (): void {
    config()->set('email-verifier.external.kickbox.api_key', '');
    config()->set('email-verifier.external.kickbox.endpoint', '');

    $verifier = resolve(KickboxEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->meta['provider'])->toBe('kickbox')
        ->and($r->meta['configured'])->toBeFalse();
});

it('fails open when kickbox returns non-2xx', function (): void {
    config()->set('email-verifier.external.kickbox.api_key', 'abc');
    config()->set('email-verifier.external.kickbox.endpoint', 'https://api.kickbox.com/v2/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.kickbox.com/*' => Http::response('', 503),
    ]);

    $verifier = resolve(KickboxEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['provider'])->toBe('kickbox')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['http_status'])->toBe(503);
});

it('accepts deliverable with score 100', function (): void {
    config()->set('email-verifier.external.kickbox.api_key', 'abc');
    config()->set('email-verifier.external.kickbox.endpoint', 'https://api.kickbox.com/v2/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (Request $request) {
        expect($request->url())->toContain('api.kickbox.com/v2/verify');
        expect($request['apikey'])->toBe('abc');
        expect($request['email'])->toBe('person@example.com');

        return Http::response([
            'success' => true,
            'result' => 'deliverable',
            'reason' => 'accepted_email',
            'role' => false,
            'free' => false,
            'disposable' => false,
            'accept_all' => false,
            'did_you_mean' => null,
            'sendex' => 0.92,
            'email' => 'person@example.com',
            'user' => 'person',
            'domain' => 'example.com',
            'message' => null,
        ], 200);
    });

    $verifier = resolve(KickboxEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->reasons)->toBe([])
        ->and($r->meta['provider'])->toBe('kickbox')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('deliverable');
});

it('rejects undeliverable (thin mapping)', function (): void {
    config()->set('email-verifier.external.kickbox.api_key', 'abc');
    config()->set('email-verifier.external.kickbox.endpoint', 'https://api.kickbox.com/v2/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.kickbox.com/*' => Http::response([
            'success' => true,
            'result' => 'undeliverable',
            'reason' => 'rejected_email',
            'role' => false,
            'free' => false,
            'disposable' => false,
            'accept_all' => false,
            'did_you_mean' => 'person@gmail.com',
            'sendex' => 0.2,
            'email' => 'person@gamil.com',
            'user' => 'person',
            'domain' => 'gamil.com',
            'message' => null,
        ], 200),
    ]);

    $verifier = resolve(KickboxEmailVerifier::class);

    $r = $verifier->verify('person@gamil.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(0)
        ->and($r->reasons)->toContain('external_rejected:rejected_email')
        ->and($r->meta['provider'])->toBe('kickbox')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('undeliverable')
        ->and($r->meta['reason'])->toBe('rejected_email');
});

it('treats risky as accepted but penalized (thin mapping)', function (): void {
    config()->set('email-verifier.external.kickbox.api_key', 'abc');
    config()->set('email-verifier.external.kickbox.endpoint', 'https://api.kickbox.com/v2/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.kickbox.com/*' => Http::response([
            'success' => true,
            'result' => 'risky',
            'reason' => 'low_quality',
            'role' => true,
            'free' => true,
            'disposable' => false,
            'accept_all' => true,
            'did_you_mean' => null,
            'sendex' => 0.3,
            'email' => 'support@catchall.tld',
            'user' => 'support',
            'domain' => 'catchall.tld',
            'message' => null,
        ], 200),
    ]);

    $verifier = resolve(KickboxEmailVerifier::class);

    $r = $verifier->verify('support@catchall.tld');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(75)
        ->and($r->reasons)->toContain('external_risky')
        ->and($r->meta['provider'])->toBe('kickbox')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('risky');
});

it('treats unknown as accepted but low confidence (thin mapping)', function (): void {
    config()->set('email-verifier.external.kickbox.api_key', 'abc');
    config()->set('email-verifier.external.kickbox.endpoint', 'https://api.kickbox.com/v2/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.kickbox.com/*' => Http::response([
            'success' => true,
            'result' => 'unknown',
            'reason' => 'timeout',
            'role' => false,
            'free' => false,
            'disposable' => false,
            'accept_all' => false,
            'did_you_mean' => null,
            'sendex' => 0.5,
            'email' => 'person@example.com',
            'user' => 'person',
            'domain' => 'example.com',
            'message' => null,
        ], 200),
    ]);

    $verifier = resolve(KickboxEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unknown')
        ->and($r->meta['provider'])->toBe('kickbox')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('unknown');
});

it('fails open when success is false', function (): void {
    config()->set('email-verifier.external.kickbox.api_key', 'abc');
    config()->set('email-verifier.external.kickbox.endpoint', 'https://api.kickbox.com/v2/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.kickbox.com/*' => Http::response([
            'success' => false,
            'message' => 'Invalid API key',
        ], 200),
    ]);

    $verifier = resolve(KickboxEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['kickbox_success'])->toBeFalse();
});

it('handles unrecognized result with default case', function (): void {
    config()->set('email-verifier.external.kickbox.api_key', 'abc');
    config()->set('email-verifier.external.kickbox.endpoint', 'https://api.kickbox.com/v2/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.kickbox.com/*' => Http::response([
            'success' => true,
            'result' => 'future_status',
            'email' => 'person@example.com',
        ], 200),
    ]);

    $verifier = resolve(KickboxEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unrecognized_status');
});

it('handles exceptions gracefully', function (): void {
    config()->set('email-verifier.external.kickbox.api_key', 'abc');
    config()->set('email-verifier.external.kickbox.endpoint', 'https://api.kickbox.com/v2/verify');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (): void {
        throw new RuntimeException('Connection timeout');
    });

    $verifier = resolve(KickboxEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_exception')
        ->and($r->meta['error'])->toBe('Connection timeout');
});
