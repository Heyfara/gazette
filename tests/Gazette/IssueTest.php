<?php

namespace App\Tests\Gazette;

use App\Gazette\Model\Issue;
use App\Gazette\Model\IssueStatus;
use App\Gazette\Model\Photo;
use App\Gazette\Model\Section;
use PHPUnit\Framework\TestCase;

final class IssueTest extends TestCase
{
    public static function issue(): Issue
    {
        return new Issue('ab12cd34', 4, 'La gazette', 'Spécial mer', '2026-11', IssueStatus::Draft, [
            new Section('s1', 'À la mer', 'notes', "Un texte.\n\nDeux.", [
                new Photo('0a3c1d6e-5b2f-4c8a-9d1e-2f3a4b5c6d71', new \DateTimeImmutable('2026-10-15 14:30'), 'Saint-Malo', 'France', ['Léo', 'Emma'], 'Léo et Emma'),
            ]),
            new Section('s2', '', '', '', []),
        ], new \DateTimeImmutable('2026-11-07 20:00:00+01:00'));
    }

    public function testRoundTripThroughArrays(): void
    {
        $issue = self::issue();

        $copy = Issue::fromArray(json_decode(json_encode($issue->toArray(), \JSON_THROW_ON_ERROR), true));

        self::assertEquals($issue, $copy);
        self::assertSame('2026-10-15T14:30:00', $issue->toArray()['sections'][0]['photos'][0]['takenAt']);
    }

    public function testSectionsCanBeChangedRemovedAndReordered(): void
    {
        $issue = self::issue();

        $changed = $issue->withSectionChanged('s2', static fn (Section $s): Section => $s->withFields(title: 'Cantine'));
        self::assertSame('Cantine', $changed->section('s2')?->title);
        self::assertSame('À la mer', $changed->section('s1')?->title, 'les autres rubriques ne bougent pas');

        $ordered = $changed->withSectionsOrdered(['s2', 's1']);
        self::assertSame(['s2', 's1'], array_map(static fn (Section $s): string => $s->id, $ordered->sections));

        self::assertCount(1, $ordered->withoutSection('s2')->sections);
        self::assertNull($issue->section('absente'));
    }

    public function testReorderMustListEverySectionOnce(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::issue()->withSectionsOrdered(['s1', 's1']);
    }

    public function testPhotosAcrossSectionsAndPlace(): void
    {
        $issue = self::issue();

        self::assertCount(1, $issue->photos());
        self::assertSame('Saint-Malo (France)', $issue->photos()[0]->place());
        self::assertSame('France', (new Photo('x', null, null, 'France', []))->place());
        self::assertNull((new Photo('x', null, null, null, []))->place());
    }

    public function testPhotoMediaIsSetEverywhereThePhotoAppears(): void
    {
        $issue = self::issue()->withSectionChanged('s2', static fn (Section $s): Section => $s->withPhotos([new Photo('0a3c1d6e-5b2f-4c8a-9d1e-2f3a4b5c6d71', null, null, null, [])]));

        $issue = $issue->withPhotoMedia('0a3c1d6e-5b2f-4c8a-9d1e-2f3a4b5c6d71', 'https://b2.test/p.jpg', 12);

        self::assertSame('https://b2.test/p.jpg', $issue->sections[0]->photos[0]->mediaUrl);
        self::assertSame(12, $issue->sections[1]->photos[0]->mediaId);
        self::assertSame('Léo et Emma', $issue->sections[0]->photos[0]->caption, 'le reste de la photo ne bouge pas');
        self::assertEquals($issue, Issue::fromArray($issue->toArray()));
    }

    public function testSentIssueIsNoLongerEditable(): void
    {
        $sent = self::issue()->withStatus(IssueStatus::Sent, new \DateTimeImmutable('2026-11-08'), 42);

        self::assertFalse($sent->status->editable());
        self::assertSame(42, $sent->listmonkCampaignId);
        self::assertTrue(self::issue()->withStatus(IssueStatus::Tested)->status->editable());
    }
}
