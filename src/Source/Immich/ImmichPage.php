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
        /** Nombre de photos de cette page (Immich ne donne pas le total de la recherche) */
        public int $count,
        public ?string $nextCursor,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'assets' => array_map(static fn (ImmichAsset $a): array => $a->toArray(), $this->assets),
            'count' => $this->count,
            'nextCursor' => $this->nextCursor,
        ];
    }
}
