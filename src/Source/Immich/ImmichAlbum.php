<?php

namespace App\Source\Immich;

final readonly class ImmichAlbum
{
    public function __construct(
        public string $id,
        public string $name,
        public int $count,
        public ?\DateTimeImmutable $start,
        public ?\DateTimeImmutable $end,
        public ?string $coverAssetId,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'count' => $this->count,
            'start' => $this->start?->format('Y-m-d'),
            'end' => $this->end?->format('Y-m-d'),
            'coverAssetId' => $this->coverAssetId,
        ];
    }
}
