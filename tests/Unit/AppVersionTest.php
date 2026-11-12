<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Asset\AppVersion;
use App\Asset\CommitVersionStrategy;
use PHPUnit\Framework\TestCase;

final class AppVersionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/workout-version-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/.git/refs/heads', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    public function testTheShortHashOfTheCheckedOutBranch(): void
    {
        file_put_contents($this->dir.'/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($this->dir.'/.git/refs/heads/main', str_repeat('ab', 20)."\n");

        self::assertSame('ababababab', (new AppVersion($this->dir))->current());
        self::assertSame('/css/app.css?v=ababababab', (new CommitVersionStrategy(new AppVersion($this->dir)))->applyVersion('/css/app.css'));
    }

    public function testPackedRefsAndDetachedHeads(): void
    {
        file_put_contents($this->dir.'/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($this->dir.'/.git/packed-refs', "# pack-refs with: peeled\n".str_repeat('cd', 20)." refs/heads/main\n");
        self::assertSame('cdcdcdcdcd', (new AppVersion($this->dir))->current());

        file_put_contents($this->dir.'/.git/HEAD', str_repeat('ef', 20)."\n");
        self::assertSame('efefefefef', (new AppVersion($this->dir))->current());
    }
}
