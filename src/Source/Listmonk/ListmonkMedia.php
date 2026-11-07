<?php

namespace App\Source\Listmonk;

/** Un fichier déposé dans la médiathèque Listmonk, et son adresse publique. */
final readonly class ListmonkMedia
{
    public function __construct(
        public int $id,
        public string $url,
    ) {
    }
}
