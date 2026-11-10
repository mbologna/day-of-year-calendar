<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Exercises day-of-year-calendar.php end to end over HTTP, via PHP's
 * built-in server. FunctionsTest.php covers the pure helpers in isolation;
 * this covers the script's own request handling and ICS output, which is
 * not reachable through a direct require() because of its exit/die calls.
 */
class CalendarFeedTest extends TestCase
{
    private const HOST = '127.0.0.1';
    private const PORT = 8099;
    private const TOKEN = 'integration-test-token';

    private static \Closure $stopServer;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            ['php', '-S', self::HOST . ':' . self::PORT, '-t', $root],
            $descriptors,
            $pipes,
            $root,
            ['AUTH_TOKEN' => self::TOKEN]
        );

        if ($process === false) {
            self::fail('Failed to start PHP built-in server for integration tests');
        }

        self::$stopServer = function () use ($process, $pipes): void {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_terminate($process);
            proc_close($process);
        };

        self::waitForServer();
    }

    public static function tearDownAfterClass(): void
    {
        (self::$stopServer)();
    }

    private static function waitForServer(): void
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $connection = @fsockopen(self::HOST, self::PORT, $errno, $errstr, 0.2);
            if ($connection !== false) {
                fclose($connection);
                return;
            }
            usleep(50_000);
        }
        self::fail('PHP built-in server did not start in time');
    }

    private static function fetch(string $query): array
    {
        $url = 'http://' . self::HOST . ':' . self::PORT . '/day-of-year-calendar.php' . $query;
        $body = file_get_contents($url, false, stream_context_create(['http' => ['ignore_errors' => true]]));
        return [$body, $http_response_header ?? []];
    }

    private static function post(array $fields): string
    {
        $url = 'http://' . self::HOST . ':' . self::PORT . '/day-of-year-calendar.php';
        return file_get_contents($url, false, stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => 'Content-Type: application/x-www-form-urlencoded',
            'content'       => http_build_query($fields),
            'ignore_errors' => true,
        ]]));
    }

    public function testMissingTokenShowsUrlBuilderForm(): void
    {
        [$body, $headers] = self::fetch('');
        $this->assertStringContainsString('200', $headers[0]);
        $this->assertStringContainsString('Get subscription link', $body);
        $this->assertStringNotContainsString('name="location"', $body);
    }

    public function testGeneratedLinkIgnoresPostedLabel(): void
    {
        // A page cached from before the label field was removed may still post it.
        $body = self::post(['generate_url' => '1', 'password' => self::TOKEN, 'location' => 'Milan']);

        $this->assertStringContainsString('id="sub-url"', $body);
        $this->assertStringContainsString('?token=' . self::TOKEN . '</div>', $body);
        $this->assertStringNotContainsString('location=', $body);
    }

    public function testInvalidTokenReturns403(): void
    {
        [$body, $headers] = self::fetch('?token=wrong-token');
        $this->assertStringContainsString('403', $headers[0]);
        $this->assertSame('Invalid access token', $body);
        $this->assertContains('Content-Type: text/plain; charset=utf-8', $headers);
        $this->assertStringNotContainsString('BEGIN:VCALENDAR', $body);
    }

    public function testValidTokenReturnsCalendarFeed(): void
    {
        [$body, $headers] = self::fetch('?token=' . self::TOKEN);

        $this->assertStringContainsString('200', $headers[0]);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('END:VCALENDAR', $body);
        $this->assertStringContainsString('BEGIN:VEVENT', $body);

        $today = date('Ymd');
        $this->assertStringContainsString("DTSTART;VALUE=DATE:{$today}", $body);
        $this->assertStringContainsString('X-WR-CALDESC:', $body);
    }

    public function testFeedStartsOnFirstOfJanuary(): void
    {
        [$body] = self::fetch('?token=' . self::TOKEN);

        $janFirst = date('Y') . '0101';
        $this->assertStringContainsString("DTSTART;VALUE=DATE:{$janFirst}", $body);
    }

    public function testCalendarLabelIsEscapedInName(): void
    {
        [$body] = self::fetch('?token=' . self::TOKEN . '&location=' . rawurlencode('Milan, Italy'));
        $this->assertStringContainsString('X-WR-CALNAME:📅 Day of Year – Milan\\, Italy', $body);
    }

    public function testHealthCheckReturnsOkWithoutToken(): void
    {
        [$body, $headers] = self::fetch('?health=1');

        $this->assertStringContainsString('200', $headers[0]);
        $decoded = json_decode($body, true);
        $this->assertSame('ok', $decoded['status'] ?? null);
        $this->assertArrayNotHasKey('version', $decoded);
        $this->assertArrayNotHasKey('php', $decoded);
    }

    public function testLegacyZoneParameterStillServesFeed(): void
    {
        [$body, $headers] = self::fetch('?token=' . self::TOKEN . '&zone=America%2FNew_York');

        $this->assertStringContainsString('200', $headers[0]);
        $this->assertStringContainsString('X-WR-TIMEZONE:America/New_York', $body);
    }
}
