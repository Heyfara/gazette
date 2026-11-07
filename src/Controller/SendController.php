<?php

namespace App\Controller;

use App\Gazette\IssueRepository;
use App\Gazette\Send\IssueSender;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** L'envoi d'un numéro par Listmonk : préparation, aperçu, test, envoi, état. */
final class SendController
{
    use SameSiteWrites;

    /** Dépose les photos et crée ou met à jour la campagne. Idempotent. */
    #[Route('/api/issues/{id}/prepare', methods: ['POST'])]
    public function prepare(string $id, Request $request, IssueSender $sender): JsonResponse
    {
        self::sameSiteOnly($request);

        return new JsonResponse(IssueView::full($sender->prepare($id)));
    }

    /** Le mail rendu par Listmonk (template compris), après préparation. */
    #[Route('/api/issues/{id}/mail-preview', methods: ['GET'])]
    public function preview(string $id, IssueSender $sender): Response
    {
        return new Response($sender->preview($id), 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /** Envoi de test aux adresses de GAZETTE_TEST_EMAILS ; le numéro passe en « testé ». */
    #[Route('/api/issues/{id}/test', methods: ['POST'])]
    public function test(string $id, Request $request, IssueSender $sender): JsonResponse
    {
        self::sameSiteOnly($request);

        return new JsonResponse(IssueView::full($sender->test($id)));
    }

    /** Envoi à la liste. Exige un test préalable ; le numéro passe en « envoyé » et se fige. */
    #[Route('/api/issues/{id}/send', methods: ['POST'])]
    public function send(string $id, Request $request, IssueSender $sender): JsonResponse
    {
        self::sameSiteOnly($request);

        return new JsonResponse(IssueView::full($sender->send($id)));
    }

    /** État de la campagne côté Listmonk (envoyés / à envoyer). */
    #[Route('/api/issues/{id}/campaign', methods: ['GET'])]
    public function state(string $id, IssueRepository $issues, IssueSender $sender): JsonResponse
    {
        return JsonResponse::fromJsonString(json_encode($sender->state($issues->get($id))?->toArray(), \JSON_THROW_ON_ERROR));
    }
}
