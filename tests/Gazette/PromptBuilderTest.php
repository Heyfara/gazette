<?php

namespace App\Tests\Gazette;

use App\Gazette\Model\Section;
use App\Gazette\Prompt\PromptBuilder;
use PHPUnit\Framework\TestCase;

final class PromptBuilderTest extends TestCase
{
    private function builder(string $template = '', string $draftTemplate = ''): PromptBuilder
    {
        if ('' === $template && '' === $draftTemplate) {
            return new PromptBuilder('', \dirname(__DIR__, 2), 'Alice (la maman), Bob (le papa), Léo et Emma (les enfants)');
        }

        return new PromptBuilder($this->file($template), '/nulle-part', 'Alice et Bob', $this->file($draftTemplate));
    }

    private function file(string $template): string
    {
        $file = tempnam(sys_get_temp_dir(), 'prompt');
        file_put_contents($file, $template);

        return $file;
    }

    public function testFillsTheDefaultTemplate(): void
    {
        $issue = IssueTest::issue();

        $prompt = $this->builder()->build($issue, $issue->sections[0]);

        self::assertStringContainsString('« La gazette », la petite gazette', $prompt);
        self::assertStringContainsString('Numéro 4, novembre 2026.', $prompt);
        self::assertStringContainsString('La famille : Alice (la maman), Bob (le papa), Léo et Emma (les enfants).', $prompt);
        self::assertStringContainsString('rubrique « À la mer »', $prompt);
        self::assertStringContainsString("1. 15/10/2026, Saint-Malo (France), personnes : Léo, Emma, légende : Léo et Emma\n", $prompt);
        self::assertStringContainsString("(notes de l'auteur) : notes\n", $prompt);
        self::assertStringContainsString("- 03/11 : On a accueilli les nouvelles poules\n- 12/11 : Emma a perdu sa première dent\n", $prompt);
        self::assertStringContainsString('garder le même ton : (aucune)', $prompt, 'première rubrique : pas de précédente');
        self::assertStringEndsWith("Réponds uniquement avec le paragraphe.\n", $prompt);
        self::assertStringNotContainsString('{{', $prompt);
    }

    public function testEmptyNotebook(): void
    {
        $issue = IssueTest::issue();
        foreach ($issue->notes as $note) {
            $issue = $issue->withoutNote($note->id);
        }

        self::assertSame("(vide)\n", $this->builder('{{carnet}}')->build($issue, $issue->sections[0]));
        self::assertSame("(vide)\n", $this->builder('', '{{carnet}}')->buildDraft($issue));
    }

    public function testDraftOfTheWholeIssue(): void
    {
        $issue = IssueTest::issue();

        $prompt = $this->builder()->buildDraft($issue);

        self::assertStringContainsString('« La gazette »', $prompt);
        self::assertStringContainsString('Numéro 4, novembre 2026.', $prompt);
        self::assertStringContainsString('La famille : Alice (la maman)', $prompt);
        self::assertStringContainsString("- 03/11 : On a accueilli les nouvelles poules\n- 12/11 : Emma a perdu sa première dent\n", $prompt);
        self::assertStringContainsString("« À la mer »\n  notes : notes\n  photos :\n  1. 15/10/2026, Saint-Malo (France), personnes : Léo, Emma, légende : Léo et Emma\n", $prompt);
        self::assertStringContainsString("« (sans titre) »\n  photos : (aucune)\n", $prompt);
        self::assertStringNotContainsString('{{', $prompt);
    }

    public function testDraftWithoutSections(): void
    {
        $issue = IssueTest::issue()->withoutSection('s1')->withoutSection('s2');

        self::assertSame("(aucune pour l'instant)\n", $this->builder('', '{{rubriques}}')->buildDraft($issue));
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

    public function testFamilyIsOptional(): void
    {
        $issue = IssueTest::issue();

        self::assertSame("(non précisée)\n", (new PromptBuilder($this->file('{{famille}}'), '/x'))->build($issue, $issue->sections[0]));
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
