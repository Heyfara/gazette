<?php

namespace App\Source\Immich;

final readonly class Image
{
    public function __construct(
        public string $bytes,
        public string $contentType,
    ) {
    }
}
