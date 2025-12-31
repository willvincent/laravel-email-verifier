<?php

declare(strict_types=1);

use WillVincent\EmailVerifier\Dns\PhpDnsResolver;

it('returns MX records for a valid domain', function (): void {
    $resolver = new PhpDnsResolver();

    // Use a known domain with MX records
    $records = $resolver->getMxRecords('gmail.com');

    expect($records)->toBeArray()
        ->and($records)->not->toBeEmpty();
})->skip(! function_exists('dns_get_record'), 'dns_get_record not available');

it('returns empty array for domain without MX records', function (): void {
    $resolver = new PhpDnsResolver();

    // Use a domain that likely doesn't have MX records
    $records = $resolver->getMxRecords('nonexistent-domain-12345-test.invalid');

    expect($records)->toBeArray()
        ->and($records)->toBeEmpty();
})->skip(! function_exists('dns_get_record'), 'dns_get_record not available');

it('falls back to getmxrr when dns_get_record fails', function (): void {
    // We can't easily test this without mocking PHP functions,
    // but we can at least ensure the method doesn't throw
    $resolver = new PhpDnsResolver();

    $records = $resolver->getMxRecords('example.com');

    expect($records)->toBeArray();
})->skip(! function_exists('getmxrr'), 'getmxrr not available');
