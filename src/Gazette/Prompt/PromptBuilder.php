<?php

namespace App\Gazette\Prompt;

use App\Gazette\Model\Issue;
use App\Gazette\Model\Photo;
use App\Gazette\Model\Section;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Le prompt « à coller » d'une rubrique : un gabarit texte (config/prompt.txt,
 * ou GAZETTE_PROMPT_FILE) rempli avec ce que l'appli sait déjà. Aucune IA ici.
 *
 * Variables : {{nom}}, {{numero}}, {{mois}}, {{rubrique}}, {{photos}}, {{notes}}, {{precedent}}.
 */
final readonly class PromptBuilder
{
    private const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    private string $file;

    public function __construct(
        #[Autowire('%env(GAZETTE_PROMPT_FILE)%')]
        string $file,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ) {
        $this->file = '' === $file ? $projectDir.'/config/prompt.txt' : $file;
    }

    public function build(Issue $issue, Section $section): string
    {
        $template = @file_get_contents($this->file);
        if (false === $template) {
            throw new \RuntimeException(\sprintf('Gabarit du prompt introuvable : %s', $this->file));
        }
        $previous = null;
        foreach ($issue->sections as $s) {
            if ($s->id === $section->id) {
                break;
            }
            $previous = $s;
        }

        return trim(strtr($template, [
            '{{nom}}' => $issue->name,
            '{{numero}}' => (string) $issue->number,
            '{{mois}}' => self::month($issue->month),
            '{{rubrique}}' => '' === $section->title ? '(sans titre)' : $section->title,
            '{{photos}}' => [] === $section->photos ? '(aucune)' : implode("\n", array_map(self::photoLine(...), array_keys($section->photos), $section->photos)),
            '{{notes}}' => '' === trim($section->notes) ? '(aucune)' : trim($section->notes),
            '{{precedent}}' => null === $previous || '' === trim($previous->text) ? '(aucune)' : trim($previous->text),
        ]))."\n";
    }

    /** « novembre 2026 » depuis « 2026-11 ». */
    public static function month(string $month): string
    {
        [$year, $m] = explode('-', $month) + [1 => '1'];

        return \sprintf('%s %s', self::MONTHS[(int) $m - 1] ?? $m, $year);
    }

    private static function photoLine(int $i, Photo $photo): string
    {
        $parts = array_filter([
            $photo->takenAt?->format('d/m/Y'),
            $photo->place(),
            [] === $photo->people ? null : 'personnes : '.implode(', ', $photo->people),
            '' === $photo->caption ? null : 'légende : '.$photo->caption,
        ]);

        return \sprintf('%d. %s', $i + 1, [] === $parts ? 'sans information' : implode(', ', $parts));
    }
}
