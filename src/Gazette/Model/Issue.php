<?php

namespace App\Gazette\Model;

/** Un numéro de la gazette : en-tête, sections ordonnées, état d'envoi. */
final readonly class Issue
{
    /**
     * @param list<Section> $sections
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
        ];
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        return new self(
            (string) $a['id'],
            (int) $a['number'],
            (string) ($a['name'] ?? ''),
            (string) ($a['title'] ?? ''),
            (string) $a['month'],
            IssueStatus::from((string) ($a['status'] ?? IssueStatus::Draft->value)),
            array_values(array_map(Section::fromArray(...), $a['sections'] ?? [])),
            new \DateTimeImmutable((string) $a['createdAt']),
            isset($a['sentAt']) ? new \DateTimeImmutable((string) $a['sentAt']) : null,
            isset($a['listmonkCampaignId']) ? (int) $a['listmonkCampaignId'] : null,
        );
    }

    /**
     * @param list<Section>|null $sections
     */
    private function copy(?string $name = null, ?string $title = null, ?string $month = null, ?IssueStatus $status = null, ?array $sections = null, ?\DateTimeImmutable $sentAt = null, ?int $listmonkCampaignId = null): self
    {
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
        );
    }
}
