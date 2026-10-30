<?php

namespace Jarryd\Sunset\Tests\Unit;

use InvalidArgumentException;
use Jarryd\Sunset\Mode;
use Jarryd\Sunset\Sunset;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SunsetTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    #[Test]
    public function it_holds_the_sunset_details()
    {
        $sunset = new Sunset('2027-03-01', replacement: 'reports.index', since: '2026-11-01', reason: 'Use the new reports', mode: 'enforce');

        $this->assertSame('2027-03-01', $sunset->date);
        $this->assertSame('reports.index', $sunset->replacement);
        $this->assertSame('2026-11-01', $sunset->since);
        $this->assertSame('Use the new reports', $sunset->reason);
        $this->assertSame(Mode::Enforce, $sunset->mode);
    }

    #[Test]
    public function only_the_date_is_required()
    {
        $sunset = new Sunset('2027-03-01');

        $this->assertNull($sunset->replacement);
        $this->assertNull($sunset->since);
        $this->assertNull($sunset->since());
        $this->assertNull($sunset->reason);
        $this->assertNull($sunset->mode);
    }

    #[Test]
    public function dates_are_midnight_in_the_default_timezone()
    {
        date_default_timezone_set('Australia/Sydney');

        $sunset = new Sunset('2027-03-01', since: '2026-11-01');

        $this->assertSame('2027-03-01 00:00:00 Australia/Sydney', $sunset->date()->format('Y-m-d H:i:s e'));
        $this->assertSame('2026-11-01 00:00:00 Australia/Sydney', $sunset->since()?->format('Y-m-d H:i:s e'));
    }

    #[Test]
    #[DataProvider('invalidDates')]
    public function it_rejects_invalid_dates(string $date)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid sunset date [{$date}]. Expected a real date in Y-m-d format, such as 2027-03-01.");

        new Sunset($date);
    }

    public static function invalidDates(): array
    {
        return [
            'day first' => ['01/03/2027'],
            'no padding' => ['2027-3-1'],
            'impossible day' => ['2027-02-30'],
            'relative' => ['tomorrow'],
            'with a time' => ['2027-03-01 09:00'],
            'empty' => [''],
        ];
    }

    #[Test]
    public function it_rejects_an_invalid_since_date()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sunset since [2026-13-01].');

        new Sunset('2027-03-01', since: '2026-13-01');
    }

    #[Test]
    public function since_cannot_be_after_the_date()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("A sunset's since date [2027-03-02] can't be after its date [2027-03-01].");

        new Sunset('2027-03-01', since: '2027-03-02');
    }

    #[Test]
    public function since_can_be_the_same_day()
    {
        $this->assertSame('2027-03-01', (new Sunset('2027-03-01', since: '2027-03-01'))->since);
    }

    #[Test]
    public function it_rejects_an_unknown_mode()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sunset mode [enforced]. Expected one of: warn, enforce.');

        new Sunset('2027-03-01', mode: 'enforced');
    }

    #[Test]
    public function it_round_trips_through_an_array()
    {
        $sunset = new Sunset('2027-03-01', replacement: 'reports.index', since: '2026-11-01', reason: 'Use the new reports', mode: Mode::Warn);

        $this->assertSame([
            'date' => '2027-03-01',
            'replacement' => 'reports.index',
            'since' => '2026-11-01',
            'reason' => 'Use the new reports',
            'mode' => 'warn',
        ], $sunset->toArray());

        $this->assertEquals($sunset, Sunset::fromArray($sunset->toArray()));
    }

    #[Test]
    public function it_restores_from_an_array_with_only_a_date()
    {
        $this->assertEquals(new Sunset('2027-03-01'), Sunset::fromArray(['date' => '2027-03-01']));
    }
}
