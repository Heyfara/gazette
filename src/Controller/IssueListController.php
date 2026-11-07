<?php

namespace App\Controller;

use App\Gazette\IssueRepository;
use App\Gazette\Model\Issue;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class IssueListController
{
    /** Tous les numéros, le plus récent d'abord. */
    #[Route('/api/issues', methods: ['GET'])]
    public function __invoke(IssueRepository $issues): JsonResponse
    {
        return new JsonResponse(array_map(static fn (Issue $i): array => IssueView::summary($i), $issues->all()));
    }
}
