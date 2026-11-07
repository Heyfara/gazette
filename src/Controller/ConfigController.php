<?php

namespace App\Controller;

use App\Gazette\Send\SendConfiguration;
use App\Source\Listmonk\ListmonkClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ConfigController
{
    /** Réglages utiles au front : nom par défaut d'un nouveau numéro, état de l'envoi. */
    #[Route('/api/config', methods: ['GET'])]
    public function __invoke(
        #[Autowire('%env(GAZETTE_NAME)%')]
        string $defaultName,
        SendConfiguration $send,
        ListmonkClient $listmonk,
    ): JsonResponse {
        return new JsonResponse(['defaultName' => $defaultName, 'maxPhotos' => 12, 'send' => $send->toArray($listmonk->configured())]);
    }
}
