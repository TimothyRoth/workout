<?php

namespace App;

final class Version
{

    private static ?string $resolved = null;

    public static function current(): string
    {
        return self::$resolved ??= self::resolve();
    }

    private static function resolve(): string
    {
        $root = dirname(__DIR__);
        $gitDir = $root . '/.git';

        $hash = self::readGitHead($gitDir);
        if ($hash !== null) {
            return substr($hash, 0, 10);
        }

        // Not a git checkout (e.g. an exported archive): fall back to the newest asset mtime.
        $latest = 0;
        foreach (glob($root . '/public/{css,js}/*.{css,js}', GLOB_BRACE) ?: [] as $file) {
            $latest = max($latest, (int)filemtime($file));
        }
        return 'm' . $latest;
    }

    private static function readGitHead(string $gitDir): ?string
    {
        // Worktree checkouts have a .git *file* pointing at the real dir.
        if (is_file($gitDir)) {
            $content = trim((string)file_get_contents($gitDir));
            if (str_starts_with($content, 'gitdir: ')) {
                $gitDir = substr($content, 8);
            }
        }

        if (!is_file($gitDir . '/HEAD')) {
            return null;
        }

        $head = trim((string)file_get_contents($gitDir . '/HEAD'));

        if (!str_starts_with($head, 'ref: ')) {
            return self::isHash($head) ? $head : null; // detached HEAD
        }

        $ref = substr($head, 5);

        if (is_file("$gitDir/$ref")) {
            $hash = trim((string)file_get_contents("$gitDir/$ref"));
            return self::isHash($hash) ? $hash : null;
        }

        if (is_file("$gitDir/packed-refs")) {
            foreach (file("$gitDir/packed-refs", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if ($line[0] === '#' || $line[0] === '^') {
                    continue;
                }
                [$hash, $name] = explode(' ', $line, 2) + [null, null];
                if ($name === $ref && self::isHash((string)$hash)) {
                    return $hash;
                }
            }
        }

        return null;
    }

    private static function isHash(string $value): bool
    {
        return (bool)preg_match('/^[0-9a-f]{40}([0-9a-f]{24})?$/', $value);
    }
}
