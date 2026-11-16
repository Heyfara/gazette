<?php

namespace App\Gazette\Model;

/** Un numéro de la gazette : en-tête, sections ordonnées, carnet du mois, état d'envoi. */
final readonly class Issue
{
    /**
     * @param list<Section> $sections
     * @param list<Note>    $notes    le carnet du mois, rangé par date
     */
    public function __construct(
        public string $id,
        public int $number,
        public string $name,
        public string $title,
        /** Mois du numéro, AAAA-MM */
        public string $month,
        public IssueStatus $status,
        public array $sections,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $sentAt = null,
        public ?int $listmonkCampaignId = null,
        public array $notes = [],
    ) {
    }

    public function withFields(?string $name = null, ?string $title = null, ?string $month = null): self
    {
        return $this->copy(name: $name, title: $title, month: $month);
    }

    public function section(string $id): ?Section
    {
        foreach ($this->sections as $section) {
            if ($section->id === $id) {
                return $section;
            }
        }

        return null;
    }

    public function withSection(Section $section): self
    {
        return $this->copy(sections: [...$this->sections, $section]);
    }

    /**
     * @param callable(Section): Section $change
     */
    public function withSectionChanged(string $id, callable $change): self
    {
        return $this->copy(sections: array_values(array_map(
            static fn (Section $s): Section => $s->id === $id ? $change($s) : $s,
            $this->sections,
        )));
    }

    public function withoutSection(string $id): self
    {
        return $this->copy(sections: array_values(array_filter($this->sections, static fn (Section $s): bool => $s->id !== $id)));
    }

    /**
     * @param list<string> $ids tous les identifiants de section, dans le nouvel ordre
     */
    public function withSectionsOrdered(array $ids): self
    {
        $current = array_map(static fn (Section $s): string => $s->id, $this->sections);
        if (array_values(array_unique($ids)) !== $ids || [] !== array_diff($current, $ids) || [] !== array_diff($ids, $current)) {
            throw new \InvalidArgumentException('L\'ordre doit reprendre chaque section une fois, sans autre.');
        }
        $byId = array_combine($current, $this->sections);

        return $this->copy(sections: array_values(array_map(static fn (string $id): Section => $byId[$id], $ids)));
    }

    /** La même photo peut être dans plusieurs rubriques : toutes reçoivent l'adresse. */
    public function withPhotoMedia(string $assetId, string $url, int $mediaId): self
    {
        return $this->copy(sections: array_values(array_map(
            static fn (Section $s): Section => $s->withPhotos(array_map(
                static fn (Photo $p): Photo => $p->assetId === $assetId ? $p->withMedia($url, $mediaId) : $p,
                $s->photos,
            )),
            $this->sections,
        )));
    }

    public function note(string $id): ?Note
    {
        foreach ($this->notes as $note) {
            if ($note->id === $id) {
                return $note;
            }
        }

        return null;
    }

    public function withNote(Note $note): self
    {
        return $this->copy(notes: [...$this->notes, $note]);
    }

    /**
     * @param callable(Note): Note $change
     */
    public function withNoteChanged(string $id, callable $change): self
    {
        return $this->copy(notes: array_map(static fn (Note $n): Note => $n->id === $id ? $change($n) : $n, $this->notes));
    }

    public function withoutNote(string $id): self
    {
        return $this->copy(notes: array_filter($this->notes, static fn (Note $n): bool => $n->id !== $id));
    }

    public function withStatus(IssueStatus $status, ?\DateTimeImmutable $sentAt = null, ?int $campaignId = null): self
    {
        return $this->copy(status: $status, sentAt: $sentAt ?? $this->sentAt, listmonkCampaignId: $campaignId ?? $this->listmonkCampaignId);
    }

    /**
     * @return list<Photo>
     */
    public function photos(): array
    {
        return array_merge(...array_map(static fn (Section $s): array => $s->photos, $this->sections), ...[[]]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'title' => $this->title,
            'month' => $this->month,
            'status' => $this->status->value,
            'sections' => array_map(static fn (Section $s): array => $s->toArray(), $this->sections),
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'sentAt' => $this->sentAt?->format(\DateTimeInterface::ATOM),
            'listmonkCampaignId' => $this->listmonkCampaignId,
            'notes' => array_map(static fn (Note $n): array => $n->toArray(), $this->notes),
        ];
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $month = (string) $a['month'];

        return new self(
            (string) $a['id'],
            (int) $a['number'],
            (string) ($a['name'] ?? ''),
            (string) ($a['title'] ?? ''),
            $month,
            IssueStatus::from((string) ($a['status'] ?? IssueStatus::Draft->value)),
            array_values(array_map(Section::fromArray(...), $a['sections'] ?? [])),
            new \DateTimeImmutable((string) $a['createdAt']),
            isset($a['sentAt']) ? new \DateTimeImmutable((string) $a['sentAt']) : null,
            isset($a['listmonkCampaignId']) ? (int) $a['listmonkCampaignId'] : null,
            [...self::oldSectionNotes($a['sections'] ?? [], $month), ...array_map(Note::fromArray(...), $a['notes'] ?? [])],
        );
    }

    /**
     * Les notes par rubrique d'avant le carnet, reprises dans le carnet au 1er
     * du mois (préfixées du titre de la rubrique) ; le fichier est réécrit
     * sans elles à la modification suivante.
     *
     * @param array<array<string, mixed>> $sections
     *
     * @return list<Note>
     */
    private static function oldSectionNotes(array $sections, string $month): array
    {
        $notes = [];
        foreach ($sections as $s) {
            $text = trim((string) ($s['notes'] ?? ''));
            if ('' !== $text) {
                $title = (string) ($s['title'] ?? '');
                $notes[] = new Note('r'.substr((string) $s['id'], 0, 7), $month.'-01', ('' === $title ? '' : \sprintf('« %s » : ', $title)).$text);
            }
        }

        return $notes;
    }

    /**
     * @param list<Section>|null $sections
     * @param array<Note>|null   $notes    rangées ici par date (même jour : ordre d'ajout)
     */
    private function copy(?string $name = null, ?string $title = null, ?string $month = null, ?IssueStatus $status = null, ?array $sections = null, ?\DateTimeImmutable $sentAt = null, ?int $listmonkCampaignId = null, ?array $notes = null): self
    {
        if (null !== $notes) {
            $notes = array_values($notes);
            usort($notes, static fn (Note $a, Note $b): int => $a->date <=> $b->date);
        }

        return new self(
            $this->id,
            $this->number,
            $name ?? $this->name,
            $title ?? $this->title,
            $month ?? $this->month,
            $status ?? $this->status,
            $sections ?? $this->sections,
            $this->createdAt,
            $sentAt ?? $this->sentAt,
            $listmonkCampaignId ?? $this->listmonkCampaignId,
            $notes ?? $this->notes,
        );
    }
}
