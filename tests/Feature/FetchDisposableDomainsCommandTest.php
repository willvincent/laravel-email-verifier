<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // Use a unique output file per test to avoid cross-test bleed.
    $file = storage_path('app/email-verifier-tests/disposable_'.Str::random(10).'.txt');

    config()->set('email-verifier.disposable.file', $file);
    config()->set('email-verifier.disposable.source_url', 'https://example.test/disposable.txt');
    config()->set('email-verifier.disposable.timeout_seconds', 5);
    config()->set('email-verifier.disposable.max_bytes', 2_000_000);

    if (is_file($file)) {
        @unlink($file);
    }

    // Ensure dir exists
    @mkdir(dirname($file), 0755, true);
});

afterEach(function (): void {
    $file = (string) config('email-verifier.disposable.file');

    if ($file !== '' && is_file($file)) {
        @unlink($file);
    }
});

it('fetches, normalizes, and writes the disposable domain list', function (): void {
    Http::fake([
        'example.test/*' => Http::response(implode("\n", [
            '# comment',
            '',
            'Mailinator.com',
            '  @TrashMail.com  ',
            '10minutemail.com extra stuff we should ignore',
            '// another comment style',
            'not_a_domain',
            'also@not-a-domain',
            '.badleadingdot.com',
            'baddomain.com.',
            'MAILINATOR.COM', // duplicate
            'tempmail.net',
        ]), 200),
    ]);

    $exit = Artisan::call('email-verifier:fetch-disposable-domains');

    expect($exit)->toBe(0);

    $file = (string) config('email-verifier.disposable.file');
    expect(is_file($file))->toBeTrue();

    $contents = mb_trim((string) file_get_contents($file));

    // expected: lowercased, unique, sorted
    expect($contents)->toBe(implode("\n", [
        '10minutemail.com',
        'mailinator.com',
        'tempmail.net',
        'trashmail.com',
    ]));
});

it('prints no changes detected and does not rewrite when unchanged', function (): void {
    $file = (string) config('email-verifier.disposable.file');

    // Seed an existing file identical to what normalization will produce
    file_put_contents($file, implode("\n", [
        '10minutemail.com',
        'mailinator.com',
        'tempmail.net',
        'trashmail.com',
    ]));

    $mtimeBefore = filemtime($file);
    expect($mtimeBefore)->not->toBeFalse();

    Http::fake([
        'example.test/*' => Http::response(implode("\n", [
            'TrashMail.com',
            'tempmail.net',
            'mailinator.com',
            '10minutemail.com',
        ]), 200),
    ]);

    $exit = Artisan::call('email-verifier:fetch-disposable-domains');

    expect($exit)->toBe(0);

    clearstatcache(true, $file);
    $mtimeAfter = filemtime($file);
    expect($mtimeAfter)->toBe($mtimeBefore);
});

it('rewrites when --force is provided even if unchanged', function (): void {
    $file = (string) config('email-verifier.disposable.file');

    file_put_contents($file, implode("\n", [
        '10minutemail.com',
        'mailinator.com',
        'tempmail.net',
        'trashmail.com',
    ]));

    // Ensure time changes are detectable on filesystems with 1s resolution
    Sleep::sleep(1);

    $mtimeBefore = filemtime($file);
    expect($mtimeBefore)->not->toBeFalse();

    Http::fake([
        'example.test/*' => Http::response(implode("\n", [
            'TrashMail.com',
            'tempmail.net',
            'mailinator.com',
            '10minutemail.com',
        ]), 200),
    ]);

    $exit = Artisan::call('email-verifier:fetch-disposable-domains', ['--force' => true]);

    expect($exit)->toBe(0);

    clearstatcache(true, $file);
    $mtimeAfter = filemtime($file);
    expect($mtimeAfter)->toBeGreaterThan($mtimeBefore);
});

it('fails when the remote returns non-2xx', function (): void {
    Http::fake([
        'example.test/*' => Http::response('', 503),
    ]);

    $exit = Artisan::call('email-verifier:fetch-disposable-domains');

    expect($exit)->toBe(1);

    $file = (string) config('email-verifier.disposable.file');
    expect(is_file($file))->toBeFalse();
});

it('fails when fetched body exceeds max_bytes', function (): void {
    config()->set('email-verifier.disposable.max_bytes', 10); // tiny

    Http::fake([
        'example.test/*' => Http::response(str_repeat('a', 11), 200),
    ]);

    $exit = Artisan::call('email-verifier:fetch-disposable-domains');

    expect($exit)->toBe(1);

    $file = (string) config('email-verifier.disposable.file');
    expect(is_file($file))->toBeFalse();
});

it('fails when fetched list normalizes to empty', function (): void {
    Http::fake([
        'example.test/*' => Http::response(implode("\n", [
            '# only comments',
            '',
            'not_a_domain',
            'also@not-a-domain',
            '.badleadingdot.com',
            'baddomain.com.',
            '// yep',
        ]), 200),
    ]);

    $exit = Artisan::call('email-verifier:fetch-disposable-domains');

    expect($exit)->toBe(1);

    $file = (string) config('email-verifier.disposable.file');
    expect(is_file($file))->toBeFalse();
});

it('fails when source_url config is empty', function (): void {
    config()->set('email-verifier.disposable.source_url', '');

    $exit = Artisan::call('email-verifier:fetch-disposable-domains');

    expect($exit)->toBe(1);
});

it('fails when file path config is empty', function (): void {
    config()->set('email-verifier.disposable.file', '');

    Http::fake([
        'example.test/*' => Http::response('example.com', 200),
    ]);

    $exit = Artisan::call('email-verifier:fetch-disposable-domains');

    expect($exit)->toBe(1);
});

it('creates directory if it does not exist', function (): void {
    $nonExistentDir = storage_path('app/email-verifier-tests/subdir-'.Str::random(10));
    $file = $nonExistentDir.'/disposable.txt';

    config()->set('email-verifier.disposable.file', $file);

    Http::fake([
        'example.test/*' => Http::response('mailinator.com', 200),
    ]);

    $exit = Artisan::call('email-verifier:fetch-disposable-domains');

    expect($exit)->toBe(0)
        ->and(is_dir($nonExistentDir))->toBeTrue()
        ->and(is_file($file))->toBeTrue();

    // Cleanup
    @unlink($file);
    @rmdir($nonExistentDir);
});

it('fails gracefully when HTTP throws exception', function (): void {
    Http::fake(function (): void {
        throw new RuntimeException('Network error');
    });

    $exit = Artisan::call('email-verifier:fetch-disposable-domains');

    expect($exit)->toBe(1);

    $file = (string) config('email-verifier.disposable.file');
    expect(is_file($file))->toBeFalse();
});

it('handles domain that becomes empty after cleanup', function (): void {
    Http::fake([
        'example.test/*' => Http::response(implode("\n", [
            '@',        // becomes empty after ltrim(@)
            '@@@@',     // becomes empty after ltrim(@)
            'example.com',
        ]), 200),
    ]);

    $exit = Artisan::call('email-verifier:fetch-disposable-domains');

    expect($exit)->toBe(0);

    $file = (string) config('email-verifier.disposable.file');
    $contents = mb_trim((string) file_get_contents($file));

    // Should only have example.com
    expect($contents)->toBe('example.com');
});
