<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests;

/**
 * The toolbar's AJAX tab lists the AJAX calls of the page being viewed. These tests run
 * the real toolbar.js in Node (tests/js/toolbar-ajax-history.cjs) and check which entries
 * of the application-wide request history end up on a page.
 */
class ToolbarAjaxHistoryTest extends TestCase
{
    /** @var array<string, mixed> */
    private static array $results = [];

    public static function setUpBeforeClass(): void
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));

        if ($node === '') {
            return;
        }

        // Only stdout is read: Node may print warnings to stderr that are not part of the result.
        $command = escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/js/toolbar-ajax-history.cjs');
        $output = shell_exec($command . ' 2>/dev/null');
        $decoded = json_decode((string) $output, true);

        self::$results = is_array($decoded)
            ? $decoded
            : ['error' => 'No result from the script, run it by hand to see why: ' . $command];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$results === []) {
            $this->markTestSkipped('Node.js is needed to run the toolbar script.');
        }

        $this->assertArrayNotHasKey('error', self::$results, 'The toolbar script failed to load: ' . (string) (self::$results['error'] ?? ''));
    }

    public function testAjaxMadeByEarlierPagesDoesNotAppearOnThisPage(): void
    {
        $this->assertSame([], self::$results['earlierPagesAjaxIsIgnored']);
        $this->assertSame([], self::$results['oneSecondBeforeThePageIsIgnored']);
    }

    public function testAjaxMadeByThisPageIsListed(): void
    {
        $this->assertSame(['mine'], self::$results['ajaxMadeByThisPageIsKept']);
        $this->assertSame(['no-referer'], self::$results['ajaxWithoutARefererIsKeptWhenItIsRecent']);
        $this->assertSame(['same-second'], self::$results['sameSecondAsThePageCounts']);
    }

    public function testAjaxFromAnotherPageIsIgnoredEvenWhenItIsRecent(): void
    {
        $this->assertSame([], self::$results['ajaxFromAnotherPageAfterLoadIsIgnored']);
        $this->assertSame([], self::$results['rootAjaxIsNotShownOnAnotherPage']);
    }

    public function testTheRefererIsComparedByPathOnly(): void
    {
        $this->assertSame(['same-page'], self::$results['refererQueryHashAndTrailingSlashAreIgnored']);
        $this->assertSame(['root'], self::$results['rootPageMatchesItsOwnReferer']);
    }

    public function testOnlyAjaxRequestsAreListed(): void
    {
        $this->assertSame([], self::$results['requestsThatAreNotAjaxAreIgnored']);
    }

    public function testMissingInformationDoesNotHideARequest(): void
    {
        $this->assertSame(['unknown-time'], self::$results['unknownTimestampIsNotDiscarded']);
        $this->assertSame(['odd-referer'], self::$results['anUnparseableRefererDoesNotHideTheRequest']);
        $this->assertSame(['old'], self::$results['withoutAPageStartOnlyTheRefererDecides']);
    }

    public function testAMixedHistoryKeepsOnlyWhatBelongsToThePage(): void
    {
        $this->assertSame(['b', 'e'], self::$results['everythingTogether']);
        $this->assertSame(0, self::$results['badHistoryIsHarmless']);
    }
}
