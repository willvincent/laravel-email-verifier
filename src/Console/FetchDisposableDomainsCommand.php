<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Http;
use Throwable;

final class FetchDisposableDomainsCommand extends Command
{
    protected $signature = 'email-verifier:fetch-disposable-domains
                            {--path= : Override the output file path}
                            {--url= : Override the source URL}
                            {--force : Force rewrite even if unchanged}';

    protected $description = 'Fetch and update the disposable email domains list';

    public function __construct(
        private readonly ConfigRepository $config,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $urlOption = $this->option('url');
        /** @var string $urlDefault */
        $urlDefault = $this->config->get('email-verifier.disposable.source_url', '');
        $url = is_string($urlOption) && $urlOption !== '' ? $urlOption : $urlDefault;

        $pathOption = $this->option('path');
        /** @var string $pathDefault */
        $pathDefault = $this->config->get('email-verifier.disposable.file', '');
        $path = is_string($pathOption) && $pathOption !== '' ? $pathOption : $pathDefault;

        /** @var int $timeout */
        $timeout = $this->config->get('email-verifier.disposable.timeout_seconds', 10);
        /** @var int $maxBytes */
        $maxBytes = $this->config->get('email-verifier.disposable.max_bytes', 2_000_000);
        $force = (bool) $this->option('force');

        if ($url === '') {
            $this->error('No source URL configured');

            return Command::FAILURE;
        }

        if ($path === '') {
            $this->error('No output file path configured');

            return Command::FAILURE;
        }

        $this->info('Fetching disposable domains from: '.$url);

        try {
            $response = Http::timeout($timeout)->get($url);

            if (! $response->successful()) {
                $this->error('Failed to fetch disposable domains: HTTP '.$response->status());

                return Command::FAILURE;
            }

            $body = $response->body();

            if (mb_strlen($body) > $maxBytes) {
                $this->error(sprintf('Response body exceeds maximum allowed size of %d bytes', $maxBytes));

                return Command::FAILURE;
            }

            $normalized = $this->normalizeDomains($body);

            if ($normalized === []) {
                $this->error('Normalized list is empty - this seems wrong');

                return Command::FAILURE;
            }

            $newContent = implode("\n", $normalized);

            // Check if content changed
            if (! $force && is_file($path)) {
                $existing = mb_trim((string) file_get_contents($path));
                if ($existing === $newContent) {
                    $this->info('No changes detected');

                    return Command::SUCCESS;
                }
            }

            // Ensure directory exists
            $dir = dirname($path);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            file_put_contents($path, $newContent);

            $this->info('Wrote '.count($normalized).(' domains to: '.$path));

            return Command::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error('Exception: '.$throwable->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizeDomains(string $content): array
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $domains = [];

        foreach ($lines as $line) {
            $line = mb_trim($line);

            // Skip empty lines
            if ($line === '') {
                continue;
            }

            // Skip comments
            if (str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, '//')) {
                continue;
            }

            // Extract first word (domain), ignore rest
            $parts = preg_split('/\s+/', $line);
            if ($parts === false) {
                continue;
            }

            $domain = mb_trim($parts[0]);

            // Clean up @ prefix if present
            $domain = mb_ltrim($domain, '@');

            // Lowercase
            $domain = mb_strtolower($domain);

            // Skip if empty after cleanup
            if ($domain === '') {
                continue;
            }

            // Skip if contains @ (not a domain)
            if (str_contains($domain, '@')) {
                continue;
            }

            // Skip if starts or ends with dot
            if (str_starts_with($domain, '.')) {
                continue;
            }

            if (str_ends_with($domain, '.')) {
                continue;
            }

            // Skip if no dot (not a valid domain)
            if (! str_contains($domain, '.')) {
                continue;
            }

            $domains[$domain] = true;
        }

        $unique = array_keys($domains);
        sort($unique);

        return $unique;
    }
}
