<?php

declare(strict_types=1);

namespace WillVincent\EmailVerifier\Disposable;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use WillVincent\EmailVerifier\Contracts\DisposableDomainChecker;

final readonly class FileBackedDisposableDomainChecker implements DisposableDomainChecker
{
    public function __construct(
        private CacheRepository $cache,
        private ConfigRepository $config,
        private Filesystem $files,
    ) {}

    public function isDisposable(string $domain): bool
    {
        $domain = mb_strtolower(mb_trim($domain));

        /** @var array{extra_domains: array<int, string>, file: string} $cfg */
        $cfg = $this->config->get('email-verifier.disposable');

        $extra = array_map(static fn (string $d): string => mb_strtolower(mb_trim($d)), $cfg['extra_domains']);
        if (in_array($domain, $extra, true)) {
            return true;
        }

        $filePath = (string) $cfg['file'];
        if ($filePath === '' || ! $this->files->exists($filePath)) {
            // If no list is present, fail open (don’t block).
            return false;
        }

        $set = $this->cache->remember(
            key: 'email_verify:disposable_domain_set:'.md5($filePath.'|'.$this->files->lastModified($filePath)),
            ttl: now()->addHours(12),
            callback: function () use ($filePath): array {
                $lines = preg_split('/\R/', (string) $this->files->get($filePath)) ?: [];
                $domains = [];

                foreach ($lines as $line) {
                    $line = mb_strtolower(mb_trim($line));
                    if ($line === '') {
                        continue;
                    }

                    if (str_starts_with($line, '#')) {
                        continue;
                    }

                    $domains[$line] = true;
                }

                return $domains;
            },
        );

        return isset($set[$domain]);
    }
}
