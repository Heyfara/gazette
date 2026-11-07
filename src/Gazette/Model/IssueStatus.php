<?php

namespace App\Gazette\Model;

/** Cycle de vie d'un numéro : rédigé, envoyé en test, envoyé à la famille. */
enum IssueStatus: string
{
    case Draft = 'brouillon';
    case Tested = 'teste';
    case Sent = 'envoye';

    public function editable(): bool
    {
        return self::Sent !== $this;
    }
}
