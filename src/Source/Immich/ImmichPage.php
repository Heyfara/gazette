<?php

namespace App\Source\Immich;

/** Une page de résultats de recherche. */
final readonly class ImmichPage
{
    /**
     * @param list<ImmichAsset> $assets
     */
    public function __construct(
        public array $assets,
        public int $total,
        public ?int $nextPage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'assets' => array_map(static fn (ImmichAsset $a): array => $a->toArray(), $this->assets),
            'total' => $this->total,
            'nextPage' => $this->nextPage,
        ];
    }
}
