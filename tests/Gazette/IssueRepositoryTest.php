<?php

namespace App\Tests\Gazette;

use App\Gazette\IssueNotFoundException;
use App\Gazette\IssueRepository;
use App\Gazette\Model\Issue;
use App\Gazette\Model\Section;
use PHPUnit\Framework\TestCase;

final class IssueRepositoryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/gazette-test-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            unlink($f);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testCreatesNumberedIssuesNewestFirst(): void
    {
        $repo = new IssueRepository($this->dir, '/nulle-part');

        $first = $repo->create('La gazette', '2026-10');
        $second = $repo->create('La gazette', '2026-11', 'Spécial Noël');

        self::assertSame(1, $first->number);
        self::assertSame(2, $second->number);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $first->id);
        self::assertSame([$second->id, $first->id], array_map(static fn (Issue $i): string => $i->id, $repo->all()));
        self::assertEquals($second, $repo->get($second->id));
        self::assertSame('0700', substr(sprintf('%o', fileperms($this->dir)), -4), 'dossier privé');
    }

    public function testDefaultDirectoryIsVarData(): void
    {
        $repo = new IssueRepository('', $this->dir);

        $repo->create('x', '2026-11');

        self::assertDirectoryExists($this->dir.'/var/data');
        foreach (glob($this->dir.'/var/data/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->dir.'/var/data');
        rmdir($this->dir.'/var');
    }

    public function testNumberingSurvivesDeletion(): void
    {
        $repo = new IssueRepository($this->dir, '/nulle-part');
        $first = $repo->create('x', '2026-10');
        $repo->delete($first->id);

        self::assertSame(2, $repo->create('x', '2026-11')->number);
        self::assertNull($repo->find($first->id));
    }

    public function testChangeRewritesUnderLockAndReturnsNewState(): void
    {
        $repo = new IssueRepository($this->dir, '/nulle-part');
        $issue = $repo->create('x', '2026-11');

        $changed = $repo->change($issue->id, static fn (Issue $i): Issue => $i->withSection(Section::blank('s1')->withFields(title: 'Cantine')));

        self::assertSame('Cantine', $changed->section('s1')?->title);
        self::assertEquals($changed, $repo->get($issue->id));
    }

    public function testUnknownIssue(): void
    {
        $repo = new IssueRepository($this->dir, '/nulle-part');

        self::assertNull($repo->find('../etc'));
        $this->expectException(IssueNotFoundException::class);
        $repo->change('deadbeef', static fn (Issue $i): Issue => $i);
    }
}
