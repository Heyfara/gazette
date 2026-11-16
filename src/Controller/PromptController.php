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
    public function section(string $id, string $sectionId, IssueRepository $issues, PromptBuilder $prompts): JsonResponse
    {
        $issue = $issues->get($id);

        return new JsonResponse(['prompt' => $prompts->build($issue, SectionController::sectionOf($issue, $sectionId))]);
    }

    /** Le prompt du premier jet de tout le numéro : carnet du mois et rubriques déjà commencées. */
    #[Route('/api/issues/{id}/prompt', methods: ['GET'])]
    public function draft(string $id, IssueRepository $issues, PromptBuilder $prompts): JsonResponse
    {
        return new JsonResponse(['prompt' => $prompts->buildDraft($issues->get($id))]);
    }
}
