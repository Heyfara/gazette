<?php

namespace App\Gazette;

/** Données refusées (champ manquant, trop long, mois mal formé…) : réponse 422 avec la liste. */
final class InvalidPayloadException extends \InvalidArgumentException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
