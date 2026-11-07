<?php

namespace App\Gazette;

use App\Gazette\Model\Issue;
use App\Gazette\Model\IssueStatus;
use App\Storage\JsonFiles;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Les numéros, un fichier JSON chacun dans GAZETTE_DATA_DIR (var/data par
 * défaut). Les modifications passent par change() : lecture, modification et
 * réécriture sous verrou, pour que deux rédacteurs ne s'écrasent pas.
 */
final readonly class IssueRepository
{
    private const COUNTER = 'compteur';

    private JsonFiles $files;
    private string $dir;

    public function __construct(
        #[Autowire('%env(GAZETTE_DATA_DIR)%')]
        string $dir,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ) {
        $this->dir = '' === $dir ? $projectDir.'/var/data' : rtrim($dir, '/');
        $this->files = new JsonFiles($this->dir);
    }

    /**
     * @return list<Issue> du plus récent au plus ancien
     */
    public function all(): array
    {
        $issues = [];
        foreach (glob($this->dir.'/*.json') ?: [] as $file) {
            $name = basename($file, '.json');
            if (self::COUNTER === $name) {
                continue;
            }
            $data = $this->files->read($name);
            if ([] !== $data) {
                $issues[] = Issue::fromArray($data);
            }
        }
        usort($issues, static fn (Issue $a, Issue $b): int => $b->number <=> $a->number);

        return $issues;
    }

    public function find(string $id): ?Issue
    {
        if (!preg_match('/^[0-9a-f]{8}$/', $id)) {
            return null;
        }
        $data = $this->files->read($id);

        return [] === $data ? null : Issue::fromArray($data);
    }

    public function get(string $id): Issue
    {
        return $this->find($id) ?? throw new IssueNotFoundException($id);
    }

    /** Nouveau numéro, numéroté à la suite du dernier créé. */
    public function create(string $name, string $month, string $title = '', ?\DateTimeImmutable $now = null): Issue
    {
        $number = 0;
        $this->files->update(self::COUNTER, static function (array $counter) use (&$number): array {
            $number = (int) ($counter['last'] ?? 0) + 1;

            return ['last' => $number];
        });
        $issue = new Issue(bin2hex(random_bytes(4)), $number, $name, $title, $month, IssueStatus::Draft, [], $now ?? new \DateTimeImmutable(date('c')));
        $this->files->update($issue->id, static fn (): array => $issue->toArray());

        return $issue;
    }

    /**
     * Modifie un numéro sous verrou et renvoie son nouvel état.
     *
     * @param callable(Issue): Issue $change
     */
    public function change(string $id, callable $change): Issue
    {
        $this->get($id);
        $result = null;
        $this->files->update($id, static function (array $data) use ($change, &$result): array {
            $result = $change(Issue::fromArray($data));

            return $result->toArray();
        });

        return $result;
    }

    public function delete(string $id): void
    {
        $this->get($id);
        $this->files->remove($id);
    }
}
