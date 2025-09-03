<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use InvalidArgumentException;

class RecurrenceServiceTest extends TestCase
{
    private RecurrenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecurrenceService();
    }

    public function test_validates_valid_rrule()
    {
        $validRules = [
            'FREQ=DAILY',
            'FREQ=WEEKLY;BYDAY=MO,WE,FR',
            'FREQ=MONTHLY;BYMONTHDAY=15',
            'FREQ=YEARLY;BYMONTH=12;BYMONTHDAY=25',
            'FREQ=DAILY;COUNT=10',
            'FREQ=WEEKLY;UNTIL=20241231T235959Z',
        ];

        foreach ($validRules as $rule) {
            $this->assertTrue(
                $this->service->validateRRule($rule),
                "Rule should be valid: {$rule}"
            );
        }
    }

    public function test_rejects_invalid_rrule()
    {
        $invalidRules = [
            'INVALID_RULE',
            'FREQ=INVALID',
            'FREQ=DAILY;INVALID=VALUE',
            '',
        ];

        foreach ($invalidRules as $rule) {
            $this->assertFalse(
                $this->service->validateRRule($rule),
                "Rule should be invalid: {$rule}"
            );
        }
    }

    public function test_expands_daily_recurrence()
    {
        $startDate = Carbon::parse('2024-01-01 09:00:00', 'UTC');
        $endDate = Carbon::parse('2024-01-01 10:00:00', 'UTC');
        $rangeStart = Carbon::parse('2024-01-01', 'UTC');
        $rangeEnd = Carbon::parse('2024-01-10', 'UTC'); // Extended range to capture all 5 instances

        $instances = $this->service->expandRecurrence(
            'FREQ=DAILY;COUNT=5',
            $startDate,
            $endDate,
            $rangeStart,
            $rangeEnd,
            [],
            'UTC'
        );

        $this->assertCount(5, $instances);
        
        // Check first instance
        $firstInstance = $instances->first();
        $this->assertEquals('2024-01-01 09:00:00', $firstInstance['start_at']->format('Y-m-d H:i:s'));
        $this->assertEquals('2024-01-01 10:00:00', $firstInstance['end_at']->format('Y-m-d H:i:s'));
        
        // Check last instance
        $lastInstance = $instances->last();
        $this->assertEquals('2024-01-05 09:00:00', $lastInstance['start_at']->format('Y-m-d H:i:s'));
        $this->assertEquals('2024-01-05 10:00:00', $lastInstance['end_at']->format('Y-m-d H:i:s'));
    }

    public function test_expands_weekly_recurrence()
    {
        $startDate = Carbon::parse('2024-01-01 09:00:00', 'UTC'); // Monday
        $endDate = Carbon::parse('2024-01-01 10:00:00', 'UTC');
        $rangeStart = Carbon::parse('2024-01-01', 'UTC');
        $rangeEnd = Carbon::parse('2024-01-15', 'UTC');

        $instances = $this->service->expandRecurrence(
            'FREQ=WEEKLY;BYDAY=MO,WE,FR',
            $startDate,
            $endDate,
            $rangeStart,
            $rangeEnd,
            [],
            'UTC'
        );

        // Should have instances on Mondays, Wednesdays, and Fridays
        $this->assertGreaterThan(0, $instances->count());
        
        // Check that all instances fall on correct days
        foreach ($instances as $instance) {
            $dayOfWeek = $instance['start_at']->dayOfWeek;
            $this->assertContains($dayOfWeek, [1, 3, 5], 'Instance should be on Monday, Wednesday, or Friday');
        }
    }

    public function test_handles_exception_dates()
    {
        $startDate = Carbon::parse('2024-01-01 09:00:00', 'UTC');
        $endDate = Carbon::parse('2024-01-01 10:00:00', 'UTC');
        $rangeStart = Carbon::parse('2024-01-01', 'UTC');
        $rangeEnd = Carbon::parse('2024-01-10', 'UTC'); // Extended range
        $exdates = ['2024-01-02', '2024-01-04'];

        $instances = $this->service->expandRecurrence(
            'FREQ=DAILY;COUNT=5',
            $startDate,
            $endDate,
            $rangeStart,
            $rangeEnd,
            $exdates,
            'UTC'
        );

        // Should have 3 instances (5 total - 2 excluded)
        $this->assertCount(3, $instances);
        
        // Check that excluded dates are not present
        $instanceDates = $instances->map(function ($instance) {
            return $instance['start_at']->format('Y-m-d');
        })->toArray();
        
        $this->assertNotContains('2024-01-02', $instanceDates);
        $this->assertNotContains('2024-01-04', $instanceDates);
    }

    public function test_creates_daily_rule()
    {
        $rule = $this->service->createDailyRule();
        $this->assertEquals('FREQ=DAILY', $rule);

        $rule = $this->service->createDailyRule(2);
        $this->assertEquals('FREQ=DAILY;INTERVAL=2', $rule);

        $until = Carbon::parse('2024-12-31 23:59:59', 'UTC');
        $rule = $this->service->createDailyRule(1, $until);
        $this->assertEquals('FREQ=DAILY;UNTIL=20241231T235959Z', $rule);

        $rule = $this->service->createDailyRule(1, null, 10);
        $this->assertEquals('FREQ=DAILY;COUNT=10', $rule);
    }

    public function test_creates_weekly_rule()
    {
        $rule = $this->service->createWeeklyRule();
        $this->assertEquals('FREQ=WEEKLY', $rule);

        $rule = $this->service->createWeeklyRule(['MO', 'WE', 'FR']);
        $this->assertEquals('FREQ=WEEKLY;BYDAY=MO,WE,FR', $rule);

        $rule = $this->service->createWeeklyRule(['MO', 'WE', 'FR'], 2);
        $this->assertEquals('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE,FR', $rule);
    }

    public function test_creates_monthly_rule()
    {
        $rule = $this->service->createMonthlyRule();
        $this->assertEquals('FREQ=MONTHLY', $rule);

        $rule = $this->service->createMonthlyRule(15);
        $this->assertEquals('FREQ=MONTHLY;BYMONTHDAY=15', $rule);

        $rule = $this->service->createMonthlyRule(null, '1MO');
        $this->assertEquals('FREQ=MONTHLY;BYDAY=1MO', $rule);
    }

    public function test_creates_yearly_rule()
    {
        $rule = $this->service->createYearlyRule();
        $this->assertEquals('FREQ=YEARLY', $rule);

        $rule = $this->service->createYearlyRule(12, 25);
        $this->assertEquals('FREQ=YEARLY;BYMONTH=12;BYMONTHDAY=25', $rule);
    }

    public function test_parses_rrule_components()
    {
        $components = $this->service->parseRRule('FREQ=WEEKLY;BYDAY=MO,WE,FR;COUNT=10');
        
        $expected = [
            'FREQ' => 'WEEKLY',
            'BYDAY' => 'MO,WE,FR',
            'COUNT' => '10',
        ];
        
        $this->assertEquals($expected, $components);
    }

    public function test_checks_excluded_dates()
    {
        $date = Carbon::parse('2024-01-02', 'UTC');
        $exdates = ['2024-01-01', '2024-01-02', '2024-01-03'];
        
        $this->assertTrue($this->service->isExcludedDate($date, $exdates));
        
        $date = Carbon::parse('2024-01-04', 'UTC');
        $this->assertFalse($this->service->isExcludedDate($date, $exdates));
    }

    public function test_gets_rrule_description()
    {
        $description = $this->service->getRRuleDescription('FREQ=DAILY');
        $this->assertIsString($description);
        $this->assertNotEmpty($description);

        $description = $this->service->getRRuleDescription('FREQ=WEEKLY;BYDAY=MO,WE,FR');
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function test_throws_exception_for_invalid_expansion()
    {
        $this->expectException(InvalidArgumentException::class);
        
        $startDate = Carbon::parse('2024-01-01 09:00:00', 'UTC');
        $endDate = Carbon::parse('2024-01-01 10:00:00', 'UTC');
        $rangeStart = Carbon::parse('2024-01-01', 'UTC');
        $rangeEnd = Carbon::parse('2024-01-05', 'UTC');

        $this->service->expandRecurrence(
            'INVALID_RRULE',
            $startDate,
            $endDate,
            $rangeStart,
            $rangeEnd,
            [],
            'UTC'
        );
    }

    public function test_handles_timezone_conversion()
    {
        $startDate = Carbon::parse('2024-01-01 09:00:00', 'America/New_York');
        $endDate = Carbon::parse('2024-01-01 10:00:00', 'America/New_York');
        $rangeStart = Carbon::parse('2024-01-01', 'America/New_York');
        $rangeEnd = Carbon::parse('2024-01-10', 'America/New_York'); // Extended range

        $instances = $this->service->expandRecurrence(
            'FREQ=DAILY;COUNT=3',
            $startDate,
            $endDate,
            $rangeStart,
            $rangeEnd,
            [],
            'America/New_York'
        );

        $this->assertCount(3, $instances);
        
        // Check that times are converted to UTC for storage
        $firstInstance = $instances->first();
        $this->assertEquals('UTC', $firstInstance['start_at']->timezone->getName());
        $this->assertEquals('UTC', $firstInstance['end_at']->timezone->getName());
    }
}
