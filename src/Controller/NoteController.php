<?php

namespace App\Controller;

use App\Gazette\InvalidPayloadException;
use App\Gazette\IssueRepository;
use App\Gazette\Model\Issue;
use App\Gazette\Model\Note;
use App\Gazette\Payload;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/** Le carnet du mois : des notes datées, prises au fil des jours, pour les prompts. */
final class NoteController
{
    use SameSiteWrites;

    private const MAX_TEXT = 1000;
    private const MAX_NOTES = 200;

    /** Nouvelle note : texte, date (défaut : aujourd'hui, heure de Paris). */
    #[Route('/api/issues/{id}/notes', methods: ['POST'])]
    public function create(string $id, Request $request, IssueRepository $issues): JsonResponse
    {
        self::sameSiteOnly($request);
        self::jsonOnly($request);
        $payload = Payload::fromRequest($request);
        $text = $payload->string('text', self::MAX_TEXT);
        $date = $payload->date('date');
        if (null === $text || '' === $text) {
            $payload->error('« text » ne peut pas être vide.');
        }
        $payload->check();
        $date ??= (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');

        return new JsonResponse(IssueView::full($issues->change($id, static function (Issue $issue) use ($text, $date): Issue {
            IssueController::editableOnly($issue);
            if (\count($issue->notes) >= self::MAX_NOTES) {
                throw new InvalidPayloadException([\sprintf('Le carnet est plein (%d notes).', self::MAX_NOTES)]);
            }

            return $issue->withNote(new Note(bin2hex(random_bytes(4)), $date, (string) $text));
        })), 201);
    }

    /** Texte et date d'une note ; chaque champ absent reste tel quel. */
    #[Route('/api/issues/{id}/notes/{noteId}', methods: ['PATCH'])]
    public function patch(string $id, string $noteId, Request $request, IssueRepository $issues): JsonResponse
    {
        self::sameSiteOnly($request);
        self::jsonOnly($request);
        $payload = Payload::fromRequest($request);
        $text = $payload->string('text', self::MAX_TEXT);
        $date = $payload->date('date');
        if ('' === $text) {
            $payload->error('« text » ne peut pas être vide.');
        }
        $payload->check();

        return new JsonResponse(IssueView::full($issues->change($id, static function (Issue $issue) use ($noteId, $text, $date): Issue {
            IssueController::editableOnly($issue);
            self::noteOf($issue, $noteId);

            return $issue->withNoteChanged($noteId, static fn (Note $n): Note => $n->withFields($date, $text));
        })));
    }

    #[Route('/api/issues/{id}/notes/{noteId}', methods: ['DELETE'])]
    public function delete(string $id, string $noteId, Request $request, IssueRepository $issues): JsonResponse
    {
        self::sameSiteOnly($request);

        return new JsonResponse(IssueView::full($issues->change($id, static function (Issue $issue) use ($noteId): Issue {
            IssueController::editableOnly($issue);
            self::noteOf($issue, $noteId);

            return $issue->withoutNote($noteId);
        })));
    }

    private static function noteOf(Issue $issue, string $noteId): Note
    {
        return $issue->note($noteId) ?? throw new NotFoundHttpException(\sprintf('Note inconnue : %s', $noteId));
    }
}
