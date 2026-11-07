<?php

namespace App\Source\Listmonk;

/** L'état d'une campagne côté Listmonk. */
final readonly class ListmonkCampaignState
{
    public function __construct(
        public int $id,
        /** draft, scheduled, running, paused, cancelled, finished */
        public string $status,
        public int $toSend,
        public int $sent,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'status' => $this->status, 'toSend' => $this->toSend, 'sent' => $this->sent];
    }
}
