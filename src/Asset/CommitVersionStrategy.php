<?php

declare(strict_types=1);

namespace App\Asset;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/** asset('/css/app.css') → "/css/app.css?v=<commit>". */
final class CommitVersionStrategy implements VersionStrategyInterface
{
    public function __construct(private readonly AppVersion $version)
    {
    }

    public function getVersion(string $path): string
    {
        return $this->version->current();
    }

    public function applyVersion(string $path): string
    {
        return $path.'?v='.$this->version->current();
    }
}
