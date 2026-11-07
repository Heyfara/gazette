<?php

namespace App\Controller;

use App\Gazette\IssueRepository;
use App\Gazette\Payload;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class IssueCreateController
{
    use SameSiteWrites;

    /** Nouveau numéro : mois (défaut : ce mois-ci), nom (défaut : GAZETTE_NAME), titre. */
    #[Route('/api/issues', methods: ['POST'])]
    public function __invoke(
        Request $request,
        IssueRepository $issues,
        #[Autowire('%env(GAZETTE_NAME)%')]
        string $defaultName,
    ): JsonResponse {
        self::sameSiteOnly($request);
        self::jsonOnly($request);
        $payload = Payload::fromRequest($request);
        $name = $payload->string('name', 80);
        $title = $payload->string('title', 120);
        $month = $payload->month('month');
        $payload->check();

        $issue = $issues->create(
            null === $name || '' === $name ? $defaultName : $name,
            $month ?? (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y-m'),
            $title ?? '',
        );

        return new JsonResponse(IssueView::full($issue), 201);
    }
}
