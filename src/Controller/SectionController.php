<?php

namespace App\Controller;

use App\Gazette\IssueRepository;
use App\Gazette\Model\Issue;
use App\Gazette\Model\Section;
use App\Gazette\Payload;
use App\Gazette\SectionPhotos;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class SectionController
{
    use SameSiteWrites;

    /** Nouvelle rubrique vide, en fin de numéro. */
    #[Route('/api/issues/{id}/sections', methods: ['POST'])]
    public function create(string $id, Request $request, IssueRepository $issues): JsonResponse
    {
        self::sameSiteOnly($request);
        self::jsonOnly($request);
        $payload = Payload::fromRequest($request);
        $title = $payload->string('title', 120);
        $payload->check();

        return new JsonResponse(IssueView::full($issues->change($id, static function (Issue $issue) use ($title): Issue {
            IssueController::editableOnly($issue);

            return $issue->withSection(Section::blank(bin2hex(random_bytes(4)))->withFields(title: $title ?? ''));
        })), 201);
    }

    /** Titre, notes, texte et photos ([{assetId, caption}], dans l'ordre) d'une rubrique. Chaque champ absent reste tel quel. */
    #[Route('/api/issues/{id}/sections/{sectionId}', methods: ['PATCH'])]
    public function patch(string $id, string $sectionId, Request $request, IssueRepository $issues, SectionPhotos $photos): JsonResponse
    {
        self::sameSiteOnly($request);
        self::jsonOnly($request);
        $payload = Payload::fromRequest($request);
        $title = $payload->string('title', 120);
        $notes = $payload->string('notes', 5000, trim: false);
        $text = $payload->string('text', 10000, trim: false);
        $wanted = $payload->objects('photos', SectionPhotos::MAX);
        $payload->check();

        return new JsonResponse(IssueView::full($issues->change($id, static function (Issue $issue) use ($sectionId, $title, $notes, $text, $wanted, $photos): Issue {
            IssueController::editableOnly($issue);
            self::sectionOf($issue, $sectionId);

            return $issue->withSectionChanged($sectionId, static function (Section $s) use ($title, $notes, $text, $wanted, $photos): Section {
                $s = $s->withFields($title, $notes, $text);

                return null === $wanted ? $s : $s->withPhotos($photos->resolve($s, $wanted));
            });
        })));
    }

    #[Route('/api/issues/{id}/sections/{sectionId}', methods: ['DELETE'])]
    public function delete(string $id, string $sectionId, Request $request, IssueRepository $issues): JsonResponse
    {
        self::sameSiteOnly($request);

        return new JsonResponse(IssueView::full($issues->change($id, static function (Issue $issue) use ($sectionId): Issue {
            IssueController::editableOnly($issue);
            self::sectionOf($issue, $sectionId);

            return $issue->withoutSection($sectionId);
        })));
    }

    /** Nouvel ordre des rubriques : { "order": [id, id, …] }, toutes présentes une fois. */
    #[Route('/api/issues/{id}/sections-order', methods: ['PUT'])]
    public function order(string $id, Request $request, IssueRepository $issues): JsonResponse
    {
        self::sameSiteOnly($request);
        self::jsonOnly($request);
        $payload = Payload::fromRequest($request);
        $order = $payload->strings('order', 100);
        if (null === $order) {
            $payload->error('« order » est obligatoire.');
        }
        $payload->check();

        return new JsonResponse(IssueView::full($issues->change($id, static function (Issue $issue) use ($order, $payload): Issue {
            IssueController::editableOnly($issue);
            try {
                return $issue->withSectionsOrdered($order ?? []);
            } catch (\InvalidArgumentException $e) {
                $payload->error($e->getMessage());
                $payload->check();
                throw $e;
            }
        })));
    }

    public static function sectionOf(Issue $issue, string $sectionId): Section
    {
        return $issue->section($sectionId) ?? throw new NotFoundHttpException(\sprintf('Rubrique inconnue : %s', $sectionId));
    }
}
