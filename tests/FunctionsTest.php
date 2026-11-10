<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{
    // ── doy_is_leap_year ──────────────────────────────────────────────────────

    public static function leapYearProvider(): array
    {
        return [
            'divisible by 4'           => [2024, true],
            'divisible by 400'         => [2000, true],
            'divisible by 100 not 400' => [1900, false],
            'divisible by 100 not 400 (2100)' => [2100, false],
            'not divisible by 4'       => [2023, false],
            'common year'              => [2025, false],
        ];
    }

    #[DataProvider('leapYearProvider')]
    public function testIsLeapYear(int $year, bool $expected): void
    {
        $this->assertSame($expected, doy_is_leap_year($year));
    }

    // ── doy_verify_token ──────────────────────────────────────────────────────

    public function testVerifyTokenAcceptsCorrectToken(): void
    {
        $this->assertTrue(doy_verify_token('test-token-bootstrap'));
    }

    public function testVerifyTokenRejectsWrongToken(): void
    {
        $this->assertFalse(doy_verify_token('wrong-token'));
    }

    public function testVerifyTokenRejectsEmptyString(): void
    {
        $this->assertFalse(doy_verify_token(''));
    }

    // ── doy_sanitize_timezone ─────────────────────────────────────────────────

    public function testSanitizeTimezoneAcceptsValidZone(): void
    {
        $this->assertSame('America/New_York', doy_sanitize_timezone('America/New_York'));
    }

    public function testSanitizeTimezoneAcceptsEuropeRome(): void
    {
        $this->assertSame('Europe/Rome', doy_sanitize_timezone('Europe/Rome'));
    }

    public function testSanitizeTimezoneFallsBackOnInvalid(): void
    {
        $this->assertSame('Europe/Rome', doy_sanitize_timezone('Not/ATimezone'));
    }

    public function testSanitizeTimezoneFallsBackOnEmpty(): void
    {
        $this->assertSame('Europe/Rome', doy_sanitize_timezone(''));
    }

    // ── doy_sanitize_text ─────────────────────────────────────────────────────

    public function testSanitizeTextStripsHtml(): void
    {
        $this->assertSame('hello world', doy_sanitize_text('<b>hello</b> world'));
    }

    public function testSanitizeTextCollapseNewlines(): void
    {
        $this->assertSame('a b', doy_sanitize_text("a\nb"));
    }

    public function testSanitizeTextTruncatesToMaxLength(): void
    {
        $this->assertSame('ab', doy_sanitize_text('abcde', 2));
    }

    public function testSanitizeTextDefaultMaxLength(): void
    {
        $long = str_repeat('a', 300);
        $this->assertSame(200, strlen(doy_sanitize_text($long)));
    }

    // ── doy_format_description ────────────────────────────────────────────────

    public function testFormatDescriptionStartsWithPrefix(): void
    {
        $output = doy_format_description('hello');
        $this->assertStringStartsWith('DESCRIPTION:hello', $output);
    }

    public function testFormatDescriptionEndsWithCrlf(): void
    {
        $output = doy_format_description('hello');
        $this->assertStringEndsWith("\r\n", $output);
    }

    public function testFormatDescriptionEscapesComma(): void
    {
        $output = doy_format_description('a,b');
        $this->assertStringContainsString('a\,b', $output);
    }

    public function testFormatDescriptionEscapesSemicolon(): void
    {
        $output = doy_format_description('a;b');
        $this->assertStringContainsString('a\;b', $output);
    }

    public function testFormatDescriptionFoldsLongLines(): void
    {
        // A line > 75 bytes must be folded with CRLF + space continuation
        $long = str_repeat('x', 100);
        $output = doy_format_description($long);
        $this->assertStringContainsString("\r\n ", $output);
    }

    public function testFormatDescriptionFoldedLinesRespect75ByteLimit(): void
    {
        $long = str_repeat('a', 200);
        foreach (explode("\r\n", rtrim($output = doy_format_description($long), "\r\n")) as $i => $line) {
            $limit = $i === 0 ? 75 : 75; // continuation lines also ≤ 75 bytes
            $this->assertLessThanOrEqual($limit, strlen($line), "Line {$i} exceeds 75 bytes");
        }
    }

    // ── doy_event_summary ────────────────────────────────────────────────────

    public static function summaryProvider(): array
    {
        return [
            'day 1 of 365'   => [1,   365, 0,  '1/365 · 0%'],
            'day 124 of 365' => [124, 365, 33, '124/365 · 33%'],
            'day 365 of 365' => [365, 365, 100, '365/365 · 100%'],
            'leap year'      => [200, 366, 55, '200/366 · 55%'],
        ];
    }

    #[DataProvider('summaryProvider')]
    public function testEventSummaryFormat(int $day, int $total, int $pct, string $expected): void
    {
        $this->assertSame($expected, doy_event_summary($day, $total, $pct));
    }

    // ── doy_escape_text / doy_fold_line ──────────────────────────────────────

    public function testEscapeTextEscapesCommaAndSemicolon(): void
    {
        $this->assertSame('Milan\\, Italy\\; HQ', doy_escape_text('Milan, Italy; HQ'));
    }

    public function testFoldLineDoesNotSplitMultibyteCharacters(): void
    {
        $output = doy_fold_line('X:' . str_repeat('▓', 40));
        foreach (explode("\r\n", rtrim($output, "\r\n")) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'));
        }
    }

    // ── doy_year_percent ─────────────────────────────────────────────────────

    public static function percentProvider(): array
    {
        return [
            'first day'           => [1,   365, 0],
            'day 124'             => [124, 365, 33],
            'second to last day'  => [364, 365, 99],
            'last day'            => [365, 365, 100],
            'leap year last day'  => [366, 366, 100],
        ];
    }

    #[DataProvider('percentProvider')]
    public function testYearPercent(int $day, int $total, int $expected): void
    {
        $this->assertSame($expected, doy_year_percent($day, $total));
    }

    // ── doy_day_stats ────────────────────────────────────────────────────────

    private static function statsFor(string $date): array
    {
        date_default_timezone_set('UTC');
        return doy_day_stats(strtotime($date));
    }

    public function testDayStatsUsesIsoWeekYear(): void
    {
        $stats = self::statsFor('2027-01-01');
        $this->assertSame(1, $stats['day']);
        $this->assertSame(53, $stats['week']);
        $this->assertSame(2026, $stats['weekYear']);
        $this->assertSame(2027, $stats['year']);
    }

    public static function quarterProvider(): array
    {
        return [
            'first day of Q1'     => ['2026-01-01', 1, 1,  90],
            'last day of leap Q1' => ['2028-03-31', 1, 91, 91],
            'first day of Q2'     => ['2026-04-01', 2, 1,  91],
            'first day of Q4'     => ['2026-10-01', 4, 1,  92],
            'last day of Q4'      => ['2026-12-31', 4, 92, 92],
        ];
    }

    #[DataProvider('quarterProvider')]
    public function testDayStatsQuarter(string $date, int $quarter, int $quarterDay, int $quarterTotal): void
    {
        $stats = self::statsFor($date);
        $this->assertSame($quarter, $stats['quarter']);
        $this->assertSame($quarterDay, $stats['quarterDay']);
        $this->assertSame($quarterTotal, $stats['quarterTotal']);
    }

    // ── doy_milestone ────────────────────────────────────────────────────────

    public static function milestoneProvider(): array
    {
        return [
            'first day'            => ['2026-01-01', 'First day of 2026'],
            'leap day'             => ['2028-02-29', 'Leap day'],
            'halfway, common'      => ['2026-07-02', 'Halfway through 2026'],
            'halfway, leap'        => ['2028-07-01', 'Halfway through 2028'],
            'day before halfway'   => ['2026-07-01', null],
            'last day'             => ['2026-12-31', null],
            'ordinary day'         => ['2026-05-04', null],
            '1 March, common year' => ['2026-03-01', null],
            '100th day'            => ['2026-04-10', '100th day of 2026'],
            '100th day, leap'      => ['2028-04-09', '100th day of 2028'],
            '200th day'            => ['2026-07-19', '200th day of 2026'],
            '300th day'            => ['2026-10-27', '300th day of 2026'],
            '100 days left'        => ['2026-09-22', null],
            'final 100 days'       => ['2026-09-23', 'Final 100 days of 2026'],
            'final 100 days, leap' => ['2028-09-23', 'Final 100 days of 2028'],
            'end of Q1'            => ['2026-03-31', 'Last day of Q1'],
            'end of Q1, leap'      => ['2028-03-31', 'Last day of Q1'],
            'end of Q2'            => ['2026-06-30', 'Last day of Q2'],
            'end of Q3, leap'      => ['2028-09-30', 'Last day of Q3'],
            'day after end of Q3'  => ['2026-10-01', null],
        ];
    }

    #[DataProvider('milestoneProvider')]
    public function testMilestone(string $date, ?string $expected): void
    {
        $stats = self::statsFor($date);
        $this->assertSame($expected, doy_milestone($stats['day'], $stats['total'], $stats['year']));
    }

    public static function milestoneCountProvider(): array
    {
        return [
            'common year' => [365, 9],
            'leap year'   => [366, 10],
        ];
    }

    /**
     * Every milestone lands on its own day: a collision would hide one and
     * lower the count.
     */
    #[DataProvider('milestoneCountProvider')]
    public function testMilestonesFallOnDistinctDays(int $totalDays, int $expected): void
    {
        $days = array_filter(
            range(1, $totalDays),
            fn(int $day) => doy_milestone($day, $totalDays, 2026) !== null
        );
        $this->assertCount($expected, $days);
    }

    // ── doy_progress_bar ─────────────────────────────────────────────────────

    public function testProgressBar(): void
    {
        $this->assertSame(str_repeat('░', 20), doy_progress_bar(0));
        $this->assertSame(str_repeat('▓', 6) . str_repeat('░', 14), doy_progress_bar(33));
        $this->assertSame(str_repeat('▓', 20), doy_progress_bar(100));
    }

    // ── doy_event_description ────────────────────────────────────────────────

    public function testEventDescriptionFormat(): void
    {
        $this->assertSame(
            "Day 124 of 365\n" . doy_progress_bar(33) . " 33%\n241 days left in 2026\nQ2 · day 34 of 91\nWeek 19",
            doy_event_description(self::statsFor('2026-05-04'))
        );
    }

    public function testEventDescriptionLeadsWithMilestone(): void
    {
        $this->assertSame(
            "Halfway through 2026\nDay 183 of 365\n" . doy_progress_bar(50)
                . " 50%\n182 days left in 2026\nQ3 · day 2 of 92\nWeek 27",
            doy_event_description(self::statsFor('2026-07-02'))
        );
    }

    public function testEventDescriptionSingularDayLeft(): void
    {
        $desc = doy_event_description(self::statsFor('2026-12-30'));
        $this->assertStringContainsString('1 day left in 2026', $desc);
    }

    public function testEventDescriptionLastDayOfYear(): void
    {
        $desc = doy_event_description(self::statsFor('2026-12-31'));
        $this->assertStringContainsString('Last day of 2026', $desc);
        $this->assertStringNotContainsString('days left', $desc);
    }

    public function testEventDescriptionNamesIsoYearWhenItDiffers(): void
    {
        $desc = doy_event_description(self::statsFor('2027-01-01'));
        $this->assertStringContainsString('Week 53 of 2026', $desc);
    }
}
