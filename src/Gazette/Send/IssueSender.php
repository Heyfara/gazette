<?php

namespace App\Gazette\Send;

use App\Gazette\Html\IssueHtmlRenderer;
use App\Gazette\IssueRepository;
use App\Gazette\Model\Issue;
use App\Gazette\Model\IssueStatus;
use App\Gazette\Model\Photo;
use App\Gazette\Prompt\PromptBuilder;
use App\Source\Immich\ImmichClient;
use App\Source\Listmonk\ListmonkCampaign;
use App\Source\Listmonk\ListmonkCampaignState;
use App\Source\Listmonk\ListmonkClient;

/**
 * L'envoi d'un numéro, en trois temps :
 *  - prepare() : chaque photo sans adresse publique est lue dans Immich,
 *    recompressée et déposée dans la médiathèque Listmonk ; la campagne est
 *    créée (ou mise à jour) avec le corps HTML du numéro ;
 *  - test() : prepare(), puis envoi de test aux adresses de GAZETTE_TEST_EMAILS ;
 *  - send() : prepare(), puis envoi réel à la liste. Le numéro passe en « envoyé ».
 */
final readonly class IssueSender
{
    public function __construct(
        private IssueRepository $issues,
        private ImmichClient $immich,
        private ListmonkClient $listmonk,
        private PhotoCompressor $compressor,
        private IssueHtmlRenderer $renderer,
        private SendConfiguration $config,
    ) {
    }

    public function prepare(string $id): Issue
    {
        $issue = $this->issues->get($id);
        self::editable($issue);
        if ([] === $issue->sections) {
            throw new NotSendableException('Le numéro n\'a aucune rubrique.');
        }
        $listId = $this->listmonk->listId($this->config->listName) ?? throw new NotSendableException(\sprintf('Liste Listmonk introuvable : « %s ». La créer, ou lancer gazette:listmonk-setup.', $this->config->listName));
        $templateId = $this->listmonk->templateId($this->config->templateName) ?? throw new NotSendableException(\sprintf('Template Listmonk introuvable : « %s ». Lancer gazette:listmonk-setup.', $this->config->templateName));

        // Photos : dépôt de celles qui n'ont pas encore d'adresse publique, hors verrou (lent).
        $uploaded = [];
        foreach ($issue->photos() as $photo) {
            if (null === $photo->mediaUrl && !isset($uploaded[$photo->assetId])) {
                $bytes = $this->compressor->compress($this->immich->thumbnail($photo->assetId, 'preview')->bytes);
                $uploaded[$photo->assetId] = $this->listmonk->uploadMedia($bytes, \sprintf('gazette-%d-%s.jpg', $issue->number, bin2hex(random_bytes(6))));
            }
        }
        $issue = $this->issues->change($id, static function (Issue $issue) use ($uploaded): Issue {
            foreach ($uploaded as $assetId => $media) {
                $issue = $issue->withPhotoMedia($assetId, $media->url, $media->id);
            }

            return $issue;
        });

        $campaign = $this->campaign($issue, $listId, $templateId);
        if (null === $issue->listmonkCampaignId) {
            $campaignId = $this->listmonk->createCampaign($campaign);
            $issue = $this->issues->change($id, static fn (Issue $i): Issue => $i->withStatus($i->status, campaignId: $campaignId));
        } else {
            $this->listmonk->updateCampaign($issue->listmonkCampaignId, $campaign);
        }

        return $issue;
    }

    /** Le mail tel que Listmonk le rendra (template compris). */
    public function preview(string $id): string
    {
        $issue = $this->prepare($id);

        return $this->listmonk->preview((int) $issue->listmonkCampaignId);
    }

    public function test(string $id): Issue
    {
        if ([] === $this->config->testEmails) {
            throw new NotSendableException('Aucune adresse de test (GAZETTE_TEST_EMAILS).');
        }
        $issue = $this->prepare($id);
        $this->listmonk->sendTest((int) $issue->listmonkCampaignId, $this->campaign($issue, null, null), $this->config->testEmails);

        return $this->issues->change($id, static fn (Issue $i): Issue => $i->withStatus(IssueStatus::Tested));
    }

    public function send(string $id): Issue
    {
        $current = $this->issues->get($id);
        if (IssueStatus::Tested !== $current->status) {
            throw new NotSendableException('Envoyer d\'abord un test, et le relire, avant l\'envoi à la famille.');
        }
        $issue = $this->prepare($id);
        $this->listmonk->start((int) $issue->listmonkCampaignId);

        return $this->issues->change($id, static fn (Issue $i): Issue => $i->withStatus(IssueStatus::Sent, new \DateTimeImmutable(date('c'))));
    }

    /** État de la campagne côté Listmonk, null si le numéro n'a pas encore de campagne. */
    public function state(Issue $issue): ?ListmonkCampaignState
    {
        return null === $issue->listmonkCampaignId ? null : $this->listmonk->campaign($issue->listmonkCampaignId);
    }

    private function campaign(Issue $issue, ?int $listId, ?int $templateId): ListmonkCampaign
    {
        $listId ??= $this->listmonk->listId($this->config->listName) ?? 0;
        $templateId ??= $this->listmonk->templateId($this->config->templateName) ?? 0;
        $month = PromptBuilder::month($issue->month);
        $subject = '' === $issue->title ? \sprintf('%s n° %d, %s', $issue->name, $issue->number, $month) : \sprintf('%s n° %d : %s', $issue->name, $issue->number, $issue->title);
        $body = $this->renderer->body($issue, static fn (Photo $p): string => (string) $p->mediaUrl);

        return new ListmonkCampaign(
            \sprintf('Gazette n° %d (%s)', $issue->number, $month),
            $subject,
            [$listId],
            $body,
            $templateId,
            '' === $this->config->fromEmail ? null : $this->config->fromEmail,
            '' === $this->config->replyTo ? [] : ['Reply-To' => $this->config->replyTo],
        );
    }

    private static function editable(Issue $issue): void
    {
        if (!$issue->status->editable()) {
            throw new NotSendableException('Ce numéro a déjà été envoyé.');
        }
        foreach ($issue->sections as $section) {
            if ('' === trim($section->text) && [] === $section->photos) {
                throw new NotSendableException(\sprintf('La rubrique « %s » est vide : ni texte ni photo.', '' === $section->title ? 'sans titre' : $section->title));
            }
        }
    }
}
