<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use WillVincent\EmailVerifier\Disposable\FileBackedDisposableDomainChecker;

beforeEach(function (): void {
    Cache::flush();
});

it('returns false when domain is not in the list', function (): void {
    $file = storage_path('app/test-disposable.txt');
    file_put_contents($file, "tempmail.com\nmailinator.com");

    config()->set('email-verifier.disposable.file', $file);
    config()->set('email-verifier.disposable.extra_domains', []);

    $checker = resolve(FileBackedDisposableDomainChecker::class);

    expect($checker->isDisposable('example.com'))->toBeFalse();

    @unlink($file);
});

it('returns true when domain is in the file list', function (): void {
    $file = storage_path('app/test-disposable.txt');
    file_put_contents($file, "tempmail.com\nmailinator.com\ntrashmail.net");

    config()->set('email-verifier.disposable.file', $file);
    config()->set('email-verifier.disposable.extra_domains', []);

    $checker = resolve(FileBackedDisposableDomainChecker::class);

    expect($checker->isDisposable('mailinator.com'))->toBeTrue()
        ->and($checker->isDisposable('tempmail.com'))->toBeTrue()
        ->and($checker->isDisposable('trashmail.net'))->toBeTrue();

    @unlink($file);
});

it('returns true when domain is in extra_domains', function (): void {
    $file = storage_path('app/test-disposable.txt');
    file_put_contents($file, 'tempmail.com');

    config()->set('email-verifier.disposable.file', $file);
    config()->set('email-verifier.disposable.extra_domains', ['custom-spam.test', '  ANOTHER-SPAM.test  ']);

    $checker = resolve(FileBackedDisposableDomainChecker::class);

    expect($checker->isDisposable('custom-spam.test'))->toBeTrue()
        ->and($checker->isDisposable('another-spam.test'))->toBeTrue()
        ->and($checker->isDisposable('ANOTHER-SPAM.TEST'))->toBeTrue();

    @unlink($file);
});

it('normalizes domain to lowercase before checking', function (): void {
    $file = storage_path('app/test-disposable.txt');
    file_put_contents($file, 'mailinator.com');

    config()->set('email-verifier.disposable.file', $file);
    config()->set('email-verifier.disposable.extra_domains', []);

    $checker = resolve(FileBackedDisposableDomainChecker::class);

    expect($checker->isDisposable('MAILINATOR.COM'))->toBeTrue()
        ->and($checker->isDisposable('Mailinator.Com'))->toBeTrue();

    @unlink($file);
});

it('returns false when file does not exist', function (): void {
    config()->set('email-verifier.disposable.file', '/nonexistent/path/file.txt');
    config()->set('email-verifier.disposable.extra_domains', []);

    $checker = resolve(FileBackedDisposableDomainChecker::class);

    expect($checker->isDisposable('mailinator.com'))->toBeFalse();
});

it('returns false when file path is empty', function (): void {
    config()->set('email-verifier.disposable.file', '');
    config()->set('email-verifier.disposable.extra_domains', []);

    $checker = resolve(FileBackedDisposableDomainChecker::class);

    expect($checker->isDisposable('mailinator.com'))->toBeFalse();
});

it('skips comments and empty lines in the file', function (): void {
    $file = storage_path('app/test-disposable.txt');
    file_put_contents($file, "# comment\n\nmailinator.com\n  \n# another comment\ntempmail.com");

    config()->set('email-verifier.disposable.file', $file);
    config()->set('email-verifier.disposable.extra_domains', []);

    $checker = resolve(FileBackedDisposableDomainChecker::class);

    expect($checker->isDisposable('mailinator.com'))->toBeTrue()
        ->and($checker->isDisposable('tempmail.com'))->toBeTrue();

    @unlink($file);
});

it('caches the domain list for 12 hours', function (): void {
    $file = storage_path('app/test-disposable.txt');
    file_put_contents($file, 'mailinator.com');

    config()->set('email-verifier.disposable.file', $file);
    config()->set('email-verifier.disposable.extra_domains', []);

    $checker = resolve(FileBackedDisposableDomainChecker::class);

    // First call - loads from file
    expect($checker->isDisposable('mailinator.com'))->toBeTrue();

    // Update file
    file_put_contents($file, 'tempmail.com');

    // Second call - should use cache, so mailinator.com is still true
    expect($checker->isDisposable('mailinator.com'))->toBeTrue()
        ->and($checker->isDisposable('tempmail.com'))->toBeFalse();

    @unlink($file);
});

it('uses different cache key when file is modified', function (): void {
    $file = storage_path('app/test-disposable.txt');
    file_put_contents($file, 'mailinator.com');

    config()->set('email-verifier.disposable.file', $file);
    config()->set('email-verifier.disposable.extra_domains', []);

    $checker = resolve(FileBackedDisposableDomainChecker::class);

    expect($checker->isDisposable('mailinator.com'))->toBeTrue();

    // Clear cache and modify file
    Cache::flush();
    Sleep::sleep(1); // Ensure mtime changes
    file_put_contents($file, 'tempmail.com');

    // Should reload from file
    expect($checker->isDisposable('mailinator.com'))->toBeFalse()
        ->and($checker->isDisposable('tempmail.com'))->toBeTrue();

    @unlink($file);
});
