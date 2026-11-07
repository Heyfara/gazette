<?php

namespace App\Gazette\Model;

/**
 * Une photo d'une section : l'asset Immich et ce qu'on en a copié (date,
 * lieu, personnes), pour le prompt et pour ne plus dépendre d'Immich ensuite.
 */
final readonly class Photo
{
    /**
     * @param list<string> $people prénoms reconnus par Immich (personnes nommées seulement)
     */
    public function __construct(
        public string $assetId,
        public ?\DateTimeImmutable $takenAt,
        public ?string $city,
        public ?string $country,
        public array $people,
        public string $caption = '',
    ) {
    }

    public function withCaption(string $caption): self
    {
        return new self($this->assetId, $this->takenAt, $this->city, $this->country, $this->people, $caption);
    }

    /** Lieu lisible : « Saint-Malo (France) », « France », ou null. */
    public function place(): ?string
    {
        return match (true) {
            null !== $this->city && null !== $this->country => \sprintf('%s (%s)', $this->city, $this->country),
            null !== $this->city => $this->city,
            default => $this->country,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'assetId' => $this->assetId,
            'takenAt' => $this->takenAt?->format('Y-m-d\TH:i:s'),
            'city' => $this->city,
            'country' => $this->country,
            'people' => $this->people,
            'caption' => $this->caption,
        ];
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        return new self(
            (string) $a['assetId'],
            isset($a['takenAt']) ? new \DateTimeImmutable((string) $a['takenAt']) : null,
            isset($a['city']) ? (string) $a['city'] : null,
            isset($a['country']) ? (string) $a['country'] : null,
            array_values(array_map('strval', $a['people'] ?? [])),
            (string) ($a['caption'] ?? ''),
        );
    }
}
