<?php

declare(strict_types=1);

namespace App\Asset;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Short hash of the checked-out commit, read from .git on every request (no hook, no build step), so
 * every deploy changes the URLs of all assets and the service worker's cache name.
 */
final class AppVersion
{
    private ?string $resolved = null;

    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
    }

    public function current(): string
    {
        return $this->resolved ??= $this->resolve();
    }

    private function resolve(): string
    {
        $hash = $this->readGitHead($this->projectDir.'/.git');
        if (null !== $hash) {
            return substr($hash, 0, 10);
        }

        // Not a git checkout: fall back to the newest asset mtime.
        $latest = 0;
        foreach (glob($this->projectDir.'/public/{css,js}/*.{css,js}', \GLOB_BRACE) ?: [] as $file) {
            $latest = max($latest, (int) filemtime($file));
        }

        return 'm'.$latest;
    }

    private function readGitHead(string $gitDir): ?string
    {
        // Worktree checkouts have a .git *file* pointing at the real directory.
        if (is_file($gitDir)) {
            $content = trim((string) file_get_contents($gitDir));
            if (str_starts_with($content, 'gitdir: ')) {
                $gitDir = substr($content, 8);
            }
        }
        if (!is_file($gitDir.'/HEAD')) {
            return null;
        }

        $head = trim((string) file_get_contents($gitDir.'/HEAD'));
        if (!str_starts_with($head, 'ref: ')) {
            return $this->isHash($head) ? $head : null;
        }

        $ref = substr($head, 5);
        if (is_file("$gitDir/$ref")) {
            $hash = trim((string) file_get_contents("$gitDir/$ref"));

            return $this->isHash($hash) ? $hash : null;
        }
        // A worktree keeps its refs in the main repository.
        $commonDir = is_file("$gitDir/commondir") ? $gitDir.'/'.trim((string) file_get_contents("$gitDir/commondir")) : $gitDir;
        if (is_file("$commonDir/$ref")) {
            $hash = trim((string) file_get_contents("$commonDir/$ref"));

            return $this->isHash($hash) ? $hash : null;
        }
        foreach (file("$commonDir/packed-refs", \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if ('#' === $line[0] || '^' === $line[0]) {
                continue;
            }
            [$hash, $name] = explode(' ', $line, 2) + [null, null];
            if ($name === $ref && $this->isHash((string) $hash)) {
                return $hash;
            }
        }

        return null;
    }

    private function isHash(string $value): bool
    {
        return 1 === preg_match('/^[0-9a-f]{40}([0-9a-f]{24})?$/', $value);
    }
}
