<?php

namespace App\Source\Immich;

/** Une photo d'Immich et les métadonnées utiles à la gazette. */
final readonly class ImmichAsset
{
    /**
     * @param list<string> $people personnes nommées reconnues sur la photo
     */
    public function __construct(
        public string $id,
        /** Heure locale de la prise de vue (sans fuseau : Immich la donne telle qu'affichée sur l'appareil) */
        public ?\DateTimeImmutable $takenAt,
        public ?string $city,
        public ?string $country,
        public array $people,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'takenAt' => $this->takenAt?->format('Y-m-d\TH:i:s'),
            'city' => $this->city,
            'country' => $this->country,
            'people' => $this->people,
        ];
    }
}
