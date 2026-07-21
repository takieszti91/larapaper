<?php

declare(strict_types=1);

use App\Models\Plugin;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Dataset describing all multiday window test cases.
 * Each item: [DTSTART, DTEND, shouldBeIncluded]
 */
dataset('multiday-window-cases', [
    // 1. Ends before window -> exclude
    ['20260220', '20260301', false],

    // 2. Starts after window -> exclude
    ['20260420', '20260425', false],

    // 3. Ends exactly on window start -> include
    ['20260220', '20260303', true],

    // 4. Ends inside window -> include
    ['20260220', '20260315', true],

    // 5. Ends exactly on window end -> include
    ['20260220', '20260409', true],

    // 6. Ends after window -> include
    ['20260220', '20260420', true],

    // 7. Starts exactly on window start, ends inside -> include
    ['20260303', '20260315', true],

    // 8. Starts exactly on window start, ends on window end -> include
    ['20260303', '20260409', true],

    // 9. Starts exactly on window start, ends after window -> include
    ['20260303', '20260420', true],

    // 10. Starts inside window, ends inside -> include
    ['20260320', '20260325', true],

    // 11. Starts inside window, ends on window end -> include
    ['20260320', '20260409', true],

    // 12. Starts inside window, ends after window -> include
    ['20260320', '20260420', true],

    // 13. Starts on window end, ends after window -> include
    ['20260409', '20260420', true],
]);

test('IcalResponseParser filters multiday events correctly within the time window', function (
    string $dtStart,
    string $dtEnd,
    bool $shouldBeIncluded
) {
    // Fixed test time: deterministic window
    // Window = 2026-03-03 .. 2026-04-09
    Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00', 'UTC'));

    // Build ICS content for the given multiday event
    $ics = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Multiday//EN
BEGIN:VEVENT
UID:multiday@example.com
SUMMARY:Multiday
DTSTART:{$dtStart}T000000Z
DTEND:{$dtEnd}T000000Z
END:VEVENT
END:VCALENDAR
ICS;

    Http::fake([
        'example.com/multiday.ics' => Http::response($ics, 200, ['Content-Type' => 'text/calendar']),
    ]);

    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://example.com/multiday.ics',
        'polling_verb' => 'get',
    ]);

    $plugin->updateDataPayload();
    $plugin->refresh();

    $events = $plugin->data_payload['ical'] ?? [];

    if ($shouldBeIncluded) {
        expect($events)->toHaveCount(1);
        expect($events[0]['SUMMARY'])->toBe('Multiday');
    } else {
        expect($events)->toBeEmpty();
    }

    Carbon::setTestNow();
})->with('multiday-window-cases');
