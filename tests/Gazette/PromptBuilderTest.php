<?php

namespace App\Tests\Gazette;

use App\Gazette\Model\Section;
use App\Gazette\Prompt\PromptBuilder;
use PHPUnit\Framework\TestCase;

final class PromptBuilderTest extends TestCase
{
    private function builder(string $template = ''): PromptBuilder
    {
        if ('' === $template) {
            return new PromptBuilder('', \dirname(__DIR__, 2));
        }
        $file = tempnam(sys_get_temp_dir(), 'prompt');
        file_put_contents($file, $template);

        return new PromptBuilder($file, '/nulle-part');
    }

    public function testFillsTheDefaultTemplate(): void
    {
        $issue = IssueTest::issue();

        $prompt = $this->builder()->build($issue, $issue->sections[0]);

        self::assertStringContainsString('« La gazette », la gazette familiale mensuelle, numéro 4 (novembre 2026)', $prompt);
        self::assertStringContainsString("Rubrique : À la mer\n", $prompt);
        self::assertStringContainsString("1. 15/10/2026, Saint-Malo (France), personnes : Léo, Emma, légende : Léo et Emma\n", $prompt);
        self::assertStringContainsString("Notes de l'auteur : notes\n", $prompt);
        self::assertStringContainsString('continuité du style : (aucune)', $prompt, 'première rubrique : pas de précédente');
        self::assertStringEndsWith("Réponds uniquement avec le paragraphe.\n", $prompt);
        self::assertStringNotContainsString('{{', $prompt);
    }

    public function testPreviousSectionTextAndEmptyFields(): void
    {
        $issue = IssueTest::issue();

        $prompt = $this->builder("{{rubrique}}|{{photos}}|{{notes}}|{{precedent}}")->build($issue, $issue->sections[1]);

        self::assertSame("(sans titre)|(aucune)|(aucune)|Un texte.\n\nDeux.\n", $prompt);
    }

    public function testPhotoWithoutAnyInformation(): void
    {
        $issue = IssueTest::issue()->withSectionChanged('s2', static fn (Section $s): Section => $s->withPhotos([new \App\Gazette\Model\Photo('x', null, null, null, [])]));

        self::assertSame("1. sans information\n", $this->builder('{{photos}}')->build($issue, $issue->sections[1]));
    }

    public function testMonthInFrench(): void
    {
        self::assertSame('janvier 2027', PromptBuilder::month('2027-01'));
        self::assertSame('août 2026', PromptBuilder::month('2026-08'));
    }

    public function testMissingTemplateFile(): void
    {
        $this->expectExceptionMessage('Gabarit du prompt introuvable');

        (new PromptBuilder('/nulle-part/prompt.txt', '/x'))->build(IssueTest::issue(), IssueTest::issue()->sections[0]);
    }
}
