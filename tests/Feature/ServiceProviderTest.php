<?php

namespace Jarryd\Sunset\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Jarryd\Sunset\SunsetServiceProvider;
use Jarryd\Sunset\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_loads_the_default_config_without_publishing()
    {
        $this->assertSame('warn', config('sunset.mode'));
        $this->assertSame(0, config('sunset.grace_days'));
        $this->assertSame(30, config('sunset.quiet_days'));
        $this->assertSame(['web', 'api'], config('sunset.middleware_groups'));
        $this->assertTrue(config('sunset.record_calls'));
        $this->assertFalse(config('sunset.record_ip'));
        $this->assertSame('sunset::notice', config('sunset.notice_view'));
    }

    #[Test]
    public function the_config_can_be_published()
    {
        $paths = ServiceProvider::pathsToPublish(SunsetServiceProvider::class, 'sunset-config');

        $this->assertCount(1, $paths);
        $this->assertSame(config_path('sunset.php'), array_values($paths)[0]);
        $this->assertFileExists(array_keys($paths)[0]);
    }
}
