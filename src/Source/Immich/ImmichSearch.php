<?php

namespace App\Source\Immich;

/** Critères de recherche de photos : un album, ou une période, avec une personne en option. */
final readonly class ImmichSearch
{
    public function __construct(
        public ?string $albumId = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?string $personId = null,
        /** Curseur de la page suivante, renvoyé par Immich (ImmichPage::$nextCursor) */
        public ?string $cursor = null,
        public int $size = 60,
    ) {
    }
}
