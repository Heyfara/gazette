<?php

namespace App\Source\Listmonk;

use App\Source\SourceUnavailableException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * API de Listmonk (v6), avec un utilisateur d'API dédié. Ce qu'on y fait :
 * déposer les photos dans la médiathèque, créer ou mettre à jour la campagne
 * du numéro, la prévisualiser, l'envoyer en test puis pour de bon.
 *
 * Permissions utiles au rôle de l'utilisateur d'API : campaigns:get,
 * campaigns:manage, media:get, media:manage, lists:get, templates:get,
 * templates:manage (commande gazette:listmonk-setup), subscribers:get.
 */
final readonly class ListmonkClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(LISTMONK_URL)%')]
        private string $baseUrl,
        #[Autowire('%env(LISTMONK_API_USER)%')]
        private string $apiUser,
        #[Autowire('%env(LISTMONK_API_TOKEN)%')]
        #[\SensitiveParameter]
        private string $apiToken,
    ) {
    }

    public function configured(): bool
    {
        return '' !== $this->baseUrl && '' !== $this->apiUser && '' !== $this->apiToken;
    }

    /** Identifiant d'une liste par son nom, null si elle n'existe pas. */
    public function listId(string $name): ?int
    {
        foreach ($this->json('GET', '/api/lists', ['query' => ['per_page' => 'all']])['results'] ?? [] as $list) {
            if (0 === strcasecmp((string) ($list['name'] ?? ''), $name)) {
                return (int) $list['id'];
            }
        }

        return null;
    }

    /** Identifiant d'un template de campagne par son nom, null s'il n'existe pas. */
    public function templateId(string $name): ?int
    {
        foreach ($this->json('GET', '/api/templates') as $template) {
            if ('campaign' === ($template['type'] ?? 'campaign') && 0 === strcasecmp((string) ($template['name'] ?? ''), $name)) {
                return (int) $template['id'];
            }
        }

        return null;
    }

    public function createTemplate(string $name, string $html): int
    {
        return (int) $this->json('POST', '/api/templates', ['json' => ['name' => $name, 'type' => 'campaign', 'subject' => '', 'body' => $html]])['id'];
    }

    public function createList(string $name): int
    {
        return (int) $this->json('POST', '/api/lists', ['json' => ['name' => $name, 'type' => 'private', 'optin' => 'single', 'tags' => ['gazette']]])['id'];
    }

    /** Dépose une image dans la médiathèque (stockée sur B2 en prod) et renvoie son adresse publique. */
    public function uploadMedia(string $bytes, string $filename, string $contentType = 'image/jpeg'): ListmonkMedia
    {
        $form = new FormDataPart(['file' => new DataPart($bytes, $filename, $contentType)]);
        $media = $this->json('POST', '/api/media', ['headers' => $form->getPreparedHeaders()->toArray(), 'body' => $form->bodyToIterable(), 'timeout' => 60]);
        $url = (string) ($media['url'] ?? $media['uri'] ?? '');
        if ('' === $url) {
            throw new SourceUnavailableException('Listmonk n\'a pas renvoyé l\'adresse du fichier déposé.');
        }

        return new ListmonkMedia((int) $media['id'], $url);
    }

    public function deleteMedia(int $id): void
    {
        $this->json('DELETE', \sprintf('/api/media/%d', $id));
    }

    public function createCampaign(ListmonkCampaign $campaign): int
    {
        return (int) $this->json('POST', '/api/campaigns', ['json' => $campaign->toRequest()])['id'];
    }

    public function updateCampaign(int $id, ListmonkCampaign $campaign): void
    {
        $this->json('PUT', \sprintf('/api/campaigns/%d', $id), ['json' => $campaign->toRequest()]);
    }

    public function campaign(int $id): ListmonkCampaignState
    {
        $c = $this->json('GET', \sprintf('/api/campaigns/%d', $id));

        return new ListmonkCampaignState((int) $c['id'], (string) ($c['status'] ?? ''), (int) ($c['to_send'] ?? 0), (int) ($c['sent'] ?? 0));
    }

    /** Le mail tel que Listmonk le rendra, template compris, pour le premier abonné venu. */
    public function preview(int $id): string
    {
        try {
            return $this->request('GET', \sprintf('/api/campaigns/%d/preview', $id))->getContent();
        } catch (ExceptionInterface $e) {
            throw new SourceUnavailableException(\sprintf('Listmonk injoignable : %s', $e->getMessage()), previous: $e);
        }
    }

    /**
     * Envoi de test aux adresses données, qui doivent être des abonnés connus de Listmonk.
     *
     * @param list<string> $emails
     */
    public function sendTest(int $id, ListmonkCampaign $campaign, array $emails): void
    {
        $this->json('POST', \sprintf('/api/campaigns/%d/test', $id), ['json' => $campaign->toRequest() + ['subscribers' => $emails]]);
    }

    /** Lance l'envoi réel (brouillon → en cours). */
    public function start(int $id): void
    {
        $this->json('PUT', \sprintf('/api/campaigns/%d/status', $id), ['json' => ['status' => 'running']]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed> le contenu de « data »
     */
    private function json(string $method, string $path, array $options = []): array
    {
        $response = $this->request($method, $path, $options);

        try {
            $data = $response->toArray()['data'] ?? [];
        } catch (ClientException $e) {
            $message = $e->getResponse()->toArray(false)['message'] ?? $e->getMessage();
            throw new SourceUnavailableException(\sprintf('Listmonk a refusé (%d) : %s', $e->getResponse()->getStatusCode(), $message), previous: $e);
        } catch (ExceptionInterface $e) {
            throw new SourceUnavailableException(\sprintf('Listmonk injoignable : %s', $e->getMessage()), previous: $e);
        }

        return \is_array($data) ? $data : ['value' => $data];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        if (!$this->configured()) {
            throw new SourceUnavailableException('Listmonk non configuré (LISTMONK_URL, LISTMONK_API_USER, LISTMONK_API_TOKEN)');
        }
        $options['headers'] = ($options['headers'] ?? []) + ['Authorization' => \sprintf('token %s:%s', $this->apiUser, $this->apiToken)];
        $options += ['timeout' => 15];

        try {
            $response = $this->httpClient->request($method, rtrim($this->baseUrl, '/').$path, $options);
            $status = $response->getStatusCode();
            if (401 === $status || 403 === $status) {
                throw new SourceUnavailableException(\sprintf('Jeton Listmonk refusé (%d) : vérifier LISTMONK_API_USER, LISTMONK_API_TOKEN et les permissions du rôle', $status));
            }

            return $response;
        } catch (ExceptionInterface $e) {
            throw new SourceUnavailableException(\sprintf('Listmonk injoignable : %s', $e->getMessage()), previous: $e);
        }
    }
}
