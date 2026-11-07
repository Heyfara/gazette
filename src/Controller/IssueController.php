<?php

namespace App\Controller;

use App\Gazette\IssueRepository;
use App\Gazette\Model\Issue;
use App\Gazette\Payload;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class IssueController
{
    use SameSiteWrites;

    /** Un numéro complet, sections et photos comprises. */
    #[Route('/api/issues/{id}', methods: ['GET'])]
    public function get(string $id, IssueRepository $issues): JsonResponse
    {
        return new JsonResponse(IssueView::full($issues->get($id)));
    }

    /** En-tête du numéro : nom, titre, mois. */
    #[Route('/api/issues/{id}', methods: ['PATCH'])]
    public function patch(string $id, Request $request, IssueRepository $issues): JsonResponse
    {
        self::sameSiteOnly($request);
        self::jsonOnly($request);
        $payload = Payload::fromRequest($request);
        $name = $payload->string('name', 80);
        $title = $payload->string('title', 120);
        $month = $payload->month('month');
        if ('' === $name) {
            $payload->error('« name » ne peut pas être vide.');
        }
        $payload->check();

        return new JsonResponse(IssueView::full($issues->change($id, static function (Issue $issue) use ($name, $title, $month): Issue {
            self::editableOnly($issue);

            return $issue->withFields($name, $title, $month);
        })));
    }

    /** Suppression d'un numéro jamais envoyé. */
    #[Route('/api/issues/{id}', methods: ['DELETE'])]
    public function delete(string $id, Request $request, IssueRepository $issues): Response
    {
        self::sameSiteOnly($request);
        self::editableOnly($issues->get($id));
        $issues->delete($id);

        return new Response('', 204);
    }

    public static function editableOnly(Issue $issue): void
    {
        if (!$issue->status->editable()) {
            throw new ConflictHttpException('Ce numéro a été envoyé : il ne se modifie plus.');
        }
    }
}
