<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // Timestamps are stored in UTC (SQLite CURRENT_TIMESTAMP) and shown in local time.
        date_default_timezone_set('Europe/Berlin');
        parent::boot();
    }
}
