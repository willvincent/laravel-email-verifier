<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WillVincent\EmailVerifier\External\ZeroBounceEmailVerifier;

it('fails open when zerobounce is not configured', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', '');
    config()->set('email-verifier.external.zerobounce.endpoint', '');

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->meta['provider'])->toBe('zerobounce')
        ->and($r->meta['configured'])->toBeFalse();
});

it('fails open when provider returns non-2xx', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', 'abc');
    config()->set('email-verifier.external.zerobounce.endpoint', 'https://api.zerobounce.net/v2/validate');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.zerobounce.net/*' => Http::response('', 503),
    ]);

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['http_status'])->toBe(503);
});

it('returns accepted 100 on valid status', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', 'abc');
    config()->set('email-verifier.external.zerobounce.endpoint', 'https://api.zerobounce.net/v2/validate');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (Request $request) {
        // Ensure query params were sent
        expect($request->url())->toContain('api.zerobounce.net/v2/validate');
        expect($request['api_key'])->toBe('abc');
        expect($request['email'])->toBe('person@example.com');

        return Http::response(['status' => 'valid'], 200);
    });

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->reasons)->toBe([]);
});

it('rejects on spamtrap status', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', 'abc');
    config()->set('email-verifier.external.zerobounce.endpoint', 'https://api.zerobounce.net/v2/validate');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.zerobounce.net/*' => Http::response(['status' => 'spamtrap'], 200),
    ]);

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(0)
        ->and($r->reasons)->toContain('external_rejected:spamtrap');
});

it('downgrades on catch-all', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', 'abc');
    config()->set('email-verifier.external.zerobounce.endpoint', 'https://api.zerobounce.net/v2/validate');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.zerobounce.net/*' => Http::response(['status' => 'catch-all'], 200),
    ]);

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(85)
        ->and($r->reasons)->toContain('external_catch_all');
});

it('treats unknown as accepted but low confidence', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', 'abc');
    config()->set('email-verifier.external.zerobounce.endpoint', 'https://api.zerobounce.net/v2/validate');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.zerobounce.net/*' => Http::response(['status' => 'unknown'], 200),
    ]);

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unknown');
});

it('rejects on invalid status', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', 'abc');
    config()->set('email-verifier.external.zerobounce.endpoint', 'https://api.zerobounce.net/v2/validate');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.zerobounce.net/*' => Http::response(['status' => 'invalid'], 200),
    ]);

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(0)
        ->and($r->reasons)->toContain('external_rejected:invalid');
});

it('rejects on abuse status', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', 'abc');
    config()->set('email-verifier.external.zerobounce.endpoint', 'https://api.zerobounce.net/v2/validate');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.zerobounce.net/*' => Http::response(['status' => 'abuse'], 200),
    ]);

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(0)
        ->and($r->reasons)->toContain('external_rejected:abuse');
});

it('rejects on do_not_mail status', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', 'abc');
    config()->set('email-verifier.external.zerobounce.endpoint', 'https://api.zerobounce.net/v2/validate');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.zerobounce.net/*' => Http::response(['status' => 'do_not_mail'], 200),
    ]);

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(0)
        ->and($r->reasons)->toContain('external_rejected:do_not_mail');
});

it('fails open when error field is present', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', 'abc');
    config()->set('email-verifier.external.zerobounce.endpoint', 'https://api.zerobounce.net/v2/validate');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.zerobounce.net/*' => Http::response(['error' => 'Invalid API key'], 200),
    ]);

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['error'])->toBe('Invalid API key');
});

it('fails open when status is empty', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', 'abc');
    config()->set('email-verifier.external.zerobounce.endpoint', 'https://api.zerobounce.net/v2/validate');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.zerobounce.net/*' => Http::response(['email' => 'person@example.com'], 200),
    ]);

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable');
});

it('handles unrecognized status with default case', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', 'abc');
    config()->set('email-verifier.external.zerobounce.endpoint', 'https://api.zerobounce.net/v2/validate');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'api.zerobounce.net/*' => Http::response(['status' => 'future_status'], 200),
    ]);

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unrecognized_status');
});

it('handles exceptions gracefully', function (): void {
    config()->set('email-verifier.external.zerobounce.api_key', 'abc');
    config()->set('email-verifier.external.zerobounce.endpoint', 'https://api.zerobounce.net/v2/validate');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (): void {
        throw new RuntimeException('Network failure');
    });

    $verifier = resolve(ZeroBounceEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_exception')
        ->and($r->meta['error'])->toBe('Network failure');
});
