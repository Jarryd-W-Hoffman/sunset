<?php

namespace Jarryd\Sunset\Tests\Feature;

use Carbon\CarbonImmutable;
use Jarryd\Sunset\Stage;
use Jarryd\Sunset\Sunset;
use Jarryd\Sunset\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StageTest extends TestCase
{
    private Sunset $sunset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sunset = new Sunset('2027-06-30');
    }

    #[Test]
    public function routes_are_deprecated_until_their_date()
    {
        $this->travelTo(CarbonImmutable::parse('2027-06-29 23:59:59'));

        $this->assertSame(Stage::Deprecated, Stage::for($this->sunset, null));
    }

    #[Test]
    public function routes_are_retired_from_their_date()
    {
        $this->travelTo(CarbonImmutable::parse('2027-06-30 00:00:00'));

        $this->assertSame(Stage::Retired, Stage::for($this->sunset, null));
    }

    #[Test]
    public function retired_routes_are_safe_to_delete_after_quiet_days_without_calls()
    {
        $this->travelTo(CarbonImmutable::parse('2027-07-30 00:00:00'));

        $this->assertSame(Stage::SafeToDelete, Stage::for($this->sunset, null));
        $this->assertSame(Stage::SafeToDelete, Stage::for($this->sunset, CarbonImmutable::parse('2027-06-29 12:00:00')));
    }

    #[Test]
    public function a_call_within_quiet_days_keeps_a_route_retired()
    {
        $this->travelTo(CarbonImmutable::parse('2027-08-15 00:00:00'));

        $this->assertSame(Stage::Retired, Stage::for($this->sunset, CarbonImmutable::parse('2027-07-20 08:00:00')));
    }

    #[Test]
    public function a_route_must_have_been_retired_for_quiet_days_first()
    {
        // No calls at all, but only retired for 29 days.
        $this->travelTo(CarbonImmutable::parse('2027-07-29 23:59:59'));

        $this->assertSame(Stage::Retired, Stage::for($this->sunset, null));
    }

    #[Test]
    public function quiet_days_is_configurable()
    {
        config(['sunset.quiet_days' => 7]);
        $this->travelTo(CarbonImmutable::parse('2027-07-07 00:00:00'));

        $this->assertSame(Stage::SafeToDelete, Stage::for($this->sunset, null));
    }
}
