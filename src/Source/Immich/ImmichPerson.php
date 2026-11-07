<?php

namespace App\Source\Immich;

final readonly class ImmichPerson
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }

    /**
     * @return array{id: string, name: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
