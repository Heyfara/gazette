<?php

namespace App\Controller;

use App\Gazette\IssueRepository;
use App\Gazette\Prompt\PromptBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PromptController
{
    /** Le prompt à coller dans ChatGPT ou Claude pour cette rubrique. */
    #[Route('/api/issues/{id}/sections/{sectionId}/prompt', methods: ['GET'])]
    public function __invoke(string $id, string $sectionId, IssueRepository $issues, PromptBuilder $prompts): JsonResponse
    {
        $issue = $issues->get($id);

        return new JsonResponse(['prompt' => $prompts->build($issue, SectionController::sectionOf($issue, $sectionId))]);
    }
}
