<?php

namespace App\Controller;

use App\Gazette\Html\IssueHtmlRenderer;
use App\Gazette\IssueRepository;
use App\Gazette\Model\Photo;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PreviewController
{
    /** Le numéro tel qu'il se lira dans le mail, photos relayées depuis Immich. */
    #[Route('/api/issues/{id}/preview', methods: ['GET'])]
    public function __invoke(string $id, IssueRepository $issues, IssueHtmlRenderer $renderer): Response
    {
        $html = $renderer->preview($issues->get($id), static fn (Photo $p): string => \sprintf('/api/immich/assets/%s/preview', $p->assetId));

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
