<?php

namespace App\Gazette\Send;

/** Le numéro ne peut pas partir en l'état (vide, déjà envoyé, pas de test, réglages manquants) : 409 avec la raison. */
final class NotSendableException extends \RuntimeException
{
}
