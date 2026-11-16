<?php

namespace App\Gazette\Model;

/**
 * Une note du carnet du mois : ce qui s'est passé un jour donné, notée sur le
 * moment pour s'en souvenir à la rédaction. Jamais dans le mail, seulement
 * dans les prompts.
 */
final readonly class Note
{
    public function __construct(
        public string $id,
        /** AAAA-MM-JJ */
        public string $date,
        public string $text,
    ) {
    }

    public function withFields(?string $date = null, ?string $text = null): self
    {
        return new self($this->id, $date ?? $this->date, $text ?? $this->text);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'date' => $this->date, 'text' => $this->text];
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        return new self((string) $a['id'], (string) $a['date'], (string) ($a['text'] ?? ''));
    }
}
