<?php

namespace App\Tests\Gazette;

use App\Gazette\Html\IssueHtmlRenderer;
use App\Gazette\Model\Photo;
use App\Gazette\Model\Section;
use PHPUnit\Framework\TestCase;

final class IssueHtmlRendererTest extends TestCase
{
    public function testBodyHasOneBlockPerSectionWithPhotosAndParagraphs(): void
    {
        $issue = IssueTest::issue();

        $html = (new IssueHtmlRenderer())->body($issue, static fn (Photo $p): string => 'https://cdn.test/'.$p->assetId.'.jpg');

        self::assertSame(2, substr_count($html, '<table role="presentation"'), 'un tableau par rubrique, même vide');
        self::assertStringContainsString('<h2 style="margin:24px 0 12px;font-size:22px;font-weight:normal;color:#b8552b">À la mer</h2>', $html);
        self::assertStringContainsString('<img src="https://cdn.test/0a3c1d6e-5b2f-4c8a-9d1e-2f3a4b5c6d71.jpg" alt="Léo et Emma" width="536"', $html);
        self::assertStringContainsString('text-align:center">Léo et Emma</p>', $html, 'légende sous la photo');
        self::assertSame(2, substr_count($html, '<p style="margin:0 0 14px'), 'un paragraphe par bloc de texte');
        self::assertStringNotContainsString('<style', $html, 'styles en ligne seulement');
    }

    public function testEscapesEverything(): void
    {
        $issue = IssueTest::issue()->withSectionChanged('s2', static fn (Section $s): Section => $s
            ->withFields(title: '<b>Titre</b>', text: "Ligne 1\nLigne 2 & co")
            ->withPhotos([new Photo('x', null, null, null, [], '"légende"')]));

        $html = (new IssueHtmlRenderer())->body($issue, static fn (): string => 'u?a=1&b=2');

        self::assertStringContainsString('&lt;b&gt;Titre&lt;/b&gt;', $html);
        self::assertStringContainsString("Ligne 1<br />\nLigne 2 &amp; co", $html);
        self::assertStringContainsString('alt="&quot;légende&quot;"', $html);
        self::assertStringContainsString('src="u?a=1&amp;b=2"', $html);
    }

    public function testPreviewWrapsTheBody(): void
    {
        $html = (new IssueHtmlRenderer())->preview(IssueTest::issue(), static fn (): string => '/img');

        self::assertStringStartsWith('<!doctype html>', $html);
        self::assertStringContainsString('<title>La gazette</title>', $html);
        self::assertStringContainsString('Numéro 4 · novembre 2026', $html);
        self::assertStringContainsString('<h1 style="margin:8px 0 0;font-size:30px;font-weight:normal">La gazette</h1><p style="margin:8px 0 0;font-size:18px;color:#444">Spécial mer</p>', $html);
        self::assertStringContainsString('width="600"', $html);
    }
}
