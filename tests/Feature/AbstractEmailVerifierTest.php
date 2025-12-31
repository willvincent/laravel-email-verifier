<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WillVincent\EmailVerifier\External\AbstractEmailVerifier;

it('fails open when abstract is not configured', function (): void {
    config()->set('email-verifier.external.abstract.api_key', '');
    config()->set('email-verifier.external.abstract.endpoint', '');

    $verifier = resolve(AbstractEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->meta['provider'])->toBe('abstract')
        ->and($r->meta['configured'])->toBeFalse();
});

it('fails open when abstract returns non-2xx', function (): void {
    config()->set('email-verifier.external.abstract.api_key', 'abc123');
    config()->set('email-verifier.external.abstract.endpoint', 'https://emailvalidation.abstractapi.com/v1');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'emailvalidation.abstractapi.com/*' => Http::response('', 503),
    ]);

    $verifier = resolve(AbstractEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['provider'])->toBe('abstract')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['http_status'])->toBe(503);
});

it('accepts deliverable with score 100', function (): void {
    config()->set('email-verifier.external.abstract.api_key', 'abc123');
    config()->set('email-verifier.external.abstract.endpoint', 'https://emailvalidation.abstractapi.com/v1');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (Request $request) {
        expect($request->url())->toContain('emailvalidation.abstractapi.com/v1');
        expect($request['api_key'])->toBe('abc123');
        expect($request['email'])->toBe('person@example.com');

        return Http::response([
            'email' => 'person@example.com',
            'autocorrect' => '',
            'deliverability' => 'DELIVERABLE',
            'quality_score' => 0.95,
            'is_valid_format' => ['value' => true, 'text' => 'TRUE'],
            'is_free_email' => ['value' => false, 'text' => 'FALSE'],
            'is_disposable_email' => ['value' => false, 'text' => 'FALSE'],
            'is_role_email' => ['value' => false, 'text' => 'FALSE'],
            'is_catchall_email' => ['value' => false, 'text' => 'FALSE'],
            'is_mx_found' => ['value' => true, 'text' => 'TRUE'],
            'is_smtp_valid' => ['value' => true, 'text' => 'TRUE'],
        ], 200);
    });

    $verifier = resolve(AbstractEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(100)
        ->and($r->reasons)->toBe([])
        ->and($r->meta['provider'])->toBe('abstract')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('deliverable');
});

it('rejects undeliverable', function (): void {
    config()->set('email-verifier.external.abstract.api_key', 'abc123');
    config()->set('email-verifier.external.abstract.endpoint', 'https://emailvalidation.abstractapi.com/v1');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'emailvalidation.abstractapi.com/*' => Http::response([
            'email' => 'person@gamil.com',
            'autocorrect' => 'person@gmail.com',
            'deliverability' => 'UNDELIVERABLE',
            'quality_score' => 0.15,
            'is_valid_format' => ['value' => true, 'text' => 'TRUE'],
            'is_free_email' => ['value' => false, 'text' => 'FALSE'],
            'is_disposable_email' => ['value' => false, 'text' => 'FALSE'],
            'is_role_email' => ['value' => false, 'text' => 'FALSE'],
            'is_catchall_email' => ['value' => false, 'text' => 'FALSE'],
            'is_mx_found' => ['value' => false, 'text' => 'FALSE'],
            'is_smtp_valid' => ['value' => false, 'text' => 'FALSE'],
        ], 200),
    ]);

    $verifier = resolve(AbstractEmailVerifier::class);

    $r = $verifier->verify('person@gamil.com');

    expect($r->accepted)->toBeFalse()
        ->and($r->score)->toBe(0)
        ->and($r->reasons)->toContain('external_rejected:undeliverable')
        ->and($r->meta['provider'])->toBe('abstract')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('undeliverable');
});

it('treats unknown as accepted but low confidence', function (): void {
    config()->set('email-verifier.external.abstract.api_key', 'abc123');
    config()->set('email-verifier.external.abstract.endpoint', 'https://emailvalidation.abstractapi.com/v1');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'emailvalidation.abstractapi.com/*' => Http::response([
            'email' => 'person@example.com',
            'autocorrect' => '',
            'deliverability' => 'UNKNOWN',
            'quality_score' => 0.50,
            'is_valid_format' => ['value' => true, 'text' => 'TRUE'],
            'is_free_email' => ['value' => false, 'text' => 'FALSE'],
            'is_disposable_email' => ['value' => false, 'text' => 'FALSE'],
            'is_role_email' => ['value' => false, 'text' => 'FALSE'],
            'is_catchall_email' => ['value' => false, 'text' => 'FALSE'],
            'is_mx_found' => ['value' => true, 'text' => 'TRUE'],
            'is_smtp_valid' => ['value' => false, 'text' => 'FALSE'],
        ], 200),
    ]);

    $verifier = resolve(AbstractEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unknown')
        ->and($r->meta['provider'])->toBe('abstract')
        ->and($r->meta['configured'])->toBeTrue()
        ->and($r->meta['status'])->toBe('unknown');
});

it('fails open when deliverability field is missing', function (): void {
    config()->set('email-verifier.external.abstract.api_key', 'abc123');
    config()->set('email-verifier.external.abstract.endpoint', 'https://emailvalidation.abstractapi.com/v1');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'emailvalidation.abstractapi.com/*' => Http::response([
            'email' => 'person@example.com',
            'error' => ['code' => 'validation_failed', 'message' => 'Invalid request'],
        ], 200),
    ]);

    $verifier = resolve(AbstractEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_provider_unavailable')
        ->and($r->meta['abstract_deliverability_missing'])->toBeTrue();
});

it('handles unrecognized deliverability with default case', function (): void {
    config()->set('email-verifier.external.abstract.api_key', 'abc123');
    config()->set('email-verifier.external.abstract.endpoint', 'https://emailvalidation.abstractapi.com/v1');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake([
        'emailvalidation.abstractapi.com/*' => Http::response([
            'email' => 'person@example.com',
            'deliverability' => 'FUTURE_STATUS',
            'quality_score' => 0.70,
        ], 200),
    ]);

    $verifier = resolve(AbstractEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(80)
        ->and($r->reasons)->toContain('external_unrecognized_status');
});

it('handles exceptions gracefully', function (): void {
    config()->set('email-verifier.external.abstract.api_key', 'abc123');
    config()->set('email-verifier.external.abstract.endpoint', 'https://emailvalidation.abstractapi.com/v1');

    /** @var HttpFactory $http */
    $http = resolve(HttpFactory::class);

    $http->fake(function (): void {
        throw new RuntimeException('Connection timeout');
    });

    $verifier = resolve(AbstractEmailVerifier::class);

    $r = $verifier->verify('person@example.com');

    expect($r->accepted)->toBeTrue()
        ->and($r->score)->toBe(90)
        ->and($r->reasons)->toContain('external_exception')
        ->and($r->meta['error'])->toBe('Connection timeout');
});
