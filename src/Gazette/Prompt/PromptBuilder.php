<?php

namespace App\Gazette\Prompt;

use App\Gazette\Model\Issue;
use App\Gazette\Model\Note;
use App\Gazette\Model\Photo;
use App\Gazette\Model\Section;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Les prompts « à coller » : des gabarits texte remplis avec ce que l'appli
 * sait déjà. Aucune IA ici.
 *
 * - rubrique (config/prompt.txt, ou GAZETTE_PROMPT_FILE) : {{nom}}, {{numero}}, {{mois}},
 *   {{famille}}, {{rubrique}}, {{photos}}, {{precedent}}, {{carnet}} ;
 * - premier jet du numéro (config/prompt-numero.txt, ou GAZETTE_DRAFT_PROMPT_FILE) :
 *   {{nom}}, {{numero}}, {{mois}}, {{famille}}, {{carnet}}, {{rubriques}}.
 *
 * {{famille}} vient de GAZETTE_FAMILY (prénoms et rôles), jamais du dépôt : l'image est publique.
 */
final readonly class PromptBuilder
{
    private const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    private string $file;
    private string $draftFile;

    public function __construct(
        #[Autowire('%env(GAZETTE_PROMPT_FILE)%')]
        string $file,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
        #[Autowire('%env(GAZETTE_FAMILY)%')]
        private string $family = '',
        #[Autowire('%env(GAZETTE_DRAFT_PROMPT_FILE)%')]
        string $draftFile = '',
    ) {
        $this->file = '' === $file ? $projectDir.'/config/prompt.txt' : $file;
        $this->draftFile = '' === $draftFile ? $projectDir.'/config/prompt-numero.txt' : $draftFile;
    }

    /** Le prompt d'une rubrique. */
    public function build(Issue $issue, Section $section): string
    {
        $template = self::template($this->file);
        $previous = null;
        foreach ($issue->sections as $s) {
            if ($s->id === $section->id) {
                break;
            }
            $previous = $s;
        }

        return trim(strtr($template, $this->common($issue) + [
            '{{rubrique}}' => self::title($section),
            '{{photos}}' => self::photos($section),
            '{{precedent}}' => null === $previous || '' === trim($previous->text) ? '(aucune)' : trim($previous->text),
        ]))."\n";
    }

    /** Le prompt du premier jet de tout le numéro, à partir du carnet et des rubriques commencées. */
    public function buildDraft(Issue $issue): string
    {
        $sections = array_map(static function (Section $s): string {
            $lines = [\sprintf('« %s »', self::title($s))];
            $lines[] = [] === $s->photos ? '  photos : (aucune)' : "  photos :\n  ".str_replace("\n", "\n  ", self::photos($s));

            return implode("\n", $lines);
        }, $issue->sections);

        return trim(strtr(self::template($this->draftFile), $this->common($issue) + [
            '{{rubriques}}' => [] === $sections ? "(aucune pour l'instant)" : implode("\n", $sections),
        ]))."\n";
    }

    /**
     * @return array<string, string>
     */
    private function common(Issue $issue): array
    {
        return [
            '{{nom}}' => $issue->name,
            '{{numero}}' => (string) $issue->number,
            '{{mois}}' => self::month($issue->month),
            '{{famille}}' => '' === trim($this->family) ? '(non précisée)' : trim($this->family),
            '{{carnet}}' => [] === $issue->notes ? '(vide)' : implode("\n", array_map(
                static fn (Note $n): string => \sprintf('- %s : %s', implode('/', array_reverse(\array_slice(explode('-', $n->date), 1))), $n->text),
                $issue->notes,
            )),
        ];
    }

    private static function template(string $file): string
    {
        $template = @file_get_contents($file);
        if (false === $template) {
            throw new \RuntimeException(\sprintf('Gabarit du prompt introuvable : %s', $file));
        }

        return $template;
    }

    private static function title(Section $section): string
    {
        return '' === $section->title ? '(sans titre)' : $section->title;
    }

    private static function photos(Section $section): string
    {
        return [] === $section->photos ? '(aucune)' : implode("\n", array_map(self::photoLine(...), array_keys($section->photos), $section->photos));
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
