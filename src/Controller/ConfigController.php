<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ConfigController
{
    /** Réglages utiles au front : nom par défaut d'un nouveau numéro. */
    #[Route('/api/config', methods: ['GET'])]
    public function __invoke(
        #[Autowire('%env(GAZETTE_NAME)%')]
        string $defaultName,
    ): JsonResponse {
        return new JsonResponse(['defaultName' => $defaultName, 'maxPhotos' => 12]);
    }
}
