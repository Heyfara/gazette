<?php

namespace App\Gazette;

final class IssueNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Numéro inconnu : %s', $id));
    }
}
