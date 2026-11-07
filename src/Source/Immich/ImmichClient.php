<?php

namespace App\Source\Immich;

use App\Source\SourceUnavailableException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * API d'Immich (3.x), avec une clé d'API dédiée : les photos sont celles du
 * propriétaire de la clé et des albums qu'on partage avec lui. Permissions :
 * album.read, asset.read, asset.view, person.read.
 */
final readonly class ImmichClient
{
    private const PERMISSIONS = 'album.read, asset.read, asset.view, person.read';
    public const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    public const SIZES = ['thumbnail', 'preview'];
    /** Une miniature peut prendre plusieurs secondes */
    private const IMAGE_TIMEOUT = 15;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(IMMICH_URL)%')]
        private string $baseUrl,
        #[Autowire('%env(IMMICH_API_KEY)%')]
        #[\SensitiveParameter]
        private string $apiKey,
        /** Autorité de certification à approuver (mkcert en dev), sinon celles du système */
        #[Autowire('%env(TLS_CA_FILE)%')]
        private ?string $caFile = null,
    ) {
    }

    /**
     * Albums visibles par la clé, du plus récent au plus ancien.
     *
     * @return list<ImmichAlbum>
     */
    public function albums(): array
    {
        $albums = array_map(static fn (array $a): ImmichAlbum => new ImmichAlbum(
            (string) $a['id'],
            (string) ($a['albumName'] ?? ''),
            (int) ($a['assetCount'] ?? 0),
            self::date($a['startDate'] ?? null),
            self::date($a['endDate'] ?? null),
            isset($a['albumThumbnailAssetId']) ? (string) $a['albumThumbnailAssetId'] : null,
        ), array_values($this->json('GET', '/api/albums')));
        usort($albums, static fn (ImmichAlbum $a, ImmichAlbum $b): int => ($b->end?->getTimestamp() ?? 0) <=> ($a->end?->getTimestamp() ?? 0));

        return $albums;
    }

    /** Photos (pas les vidéos) de la frise, les plus récentes d'abord ; la page suivante par son curseur. */
    public function search(ImmichSearch $search): ImmichPage
    {
        $filter = ['visibility' => ['eq' => 'timeline'], 'type' => ['eq' => 'IMAGE']];
        if (null !== $search->albumId) {
            $filter['albumIds'] = ['any' => [$search->albumId]];
        }
        if (null !== $search->from || null !== $search->to) {
            $filter['takenAt'] = array_filter([
                'gte' => $search->from?->format('Y-m-d\T00:00:00P'),
                'lte' => $search->to?->format('Y-m-d\T23:59:59P'),
            ]);
        }
        if (null !== $search->personId) {
            $filter['personIds'] = ['all' => [$search->personId]];
        }

        $page = $this->json('POST', '/api/search/metadata', ['json' => array_filter([
            'size' => $search->size,
            'cursor' => $search->cursor,
            'orderBy' => ['field' => 'fileCreatedAt', 'direction' => 'desc'],
            'filter' => $filter,
            'withExif' => true,
            'withPeople' => true,
        ], static fn ($v): bool => null !== $v)]);
        $assets = $page['assets'] ?? [];

        return new ImmichPage(
            array_map(self::toAsset(...), array_values($assets['items'] ?? [])),
            (int) ($assets['count'] ?? 0),
            isset($assets['nextCursor']) && '' !== $assets['nextCursor'] ? (string) $assets['nextCursor'] : null,
        );
    }

    /**
     * Personnes nommées dans Immich, par ordre alphabétique.
     *
     * @return list<ImmichPerson>
     */
    public function people(): array
    {
        $people = [];
        foreach ($this->json('GET', '/api/people', ['query' => ['withHidden' => 'false', 'size' => 1000]])['people'] ?? [] as $p) {
            $name = trim((string) ($p['name'] ?? ''));
            if ('' !== $name) {
                $people[] = new ImmichPerson((string) $p['id'], $name);
            }
        }
        usort($people, static fn (ImmichPerson $a, ImmichPerson $b): int => strcoll($a->name, $b->name));

        return $people;
    }

    public function asset(string $assetId): ImmichAsset
    {
        self::assertUuid($assetId);

        return self::toAsset($this->json('GET', \sprintf('/api/assets/%s', $assetId)));
    }

    /**
     * @param 'thumbnail'|'preview' $size
     */
    public function thumbnail(string $assetId, string $size): Image
    {
        self::assertUuid($assetId);
        if (!\in_array($size, self::SIZES, true)) {
            throw new \InvalidArgumentException(\sprintf('Taille de miniature inconnue : %s', $size));
        }

        return $this->image(\sprintf('/api/assets/%s/thumbnail', $assetId), ['size' => $size]);
    }

    public function personThumbnail(string $personId): Image
    {
        self::assertUuid($personId);

        return $this->image(\sprintf('/api/people/%s/thumbnail', $personId));
    }

    /**
     * @param array<string, mixed> $a
     */
    private static function toAsset(array $a): ImmichAsset
    {
        $exif = \is_array($a['exifInfo'] ?? null) ? $a['exifInfo'] : [];
        $people = [];
        foreach ($a['people'] ?? [] as $p) {
            $name = trim((string) ($p['name'] ?? ''));
            if ('' !== $name && !\in_array($name, $people, true)) {
                $people[] = $name;
            }
        }
        // localDateTime est l'heure de l'appareil, marquée « Z » à tort : on garde l'heure telle quelle.
        $local = $a['localDateTime'] ?? $a['fileCreatedAt'] ?? null;

        return new ImmichAsset(
            (string) $a['id'],
            null === $local ? null : new \DateTimeImmutable(substr((string) $local, 0, 19)),
            self::text($exif['city'] ?? null),
            self::text($exif['country'] ?? null),
            $people,
        );
    }

    private static function text(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return '' === $text ? null : $text;
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        return null === $value || '' === $value ? null : new \DateTimeImmutable(substr((string) $value, 0, 19));
    }

    private static function assertUuid(string $id): void
    {
        if (!preg_match(self::UUID, $id)) {
            throw new \InvalidArgumentException(\sprintf('Identifiant Immich invalide : %s', $id));
        }
    }

    /**
     * @param array<string, string> $query
     */
    private function image(string $path, array $query = []): Image
    {
        $response = $this->request('GET', $path, ['query' => $query, 'timeout' => self::IMAGE_TIMEOUT]);

        try {
            return new Image($response->getContent(), $response->getHeaders()['content-type'][0] ?? 'application/octet-stream');
        } catch (ExceptionInterface $e) {
            throw new SourceUnavailableException(\sprintf('Immich injoignable : %s', $e->getMessage()), previous: $e);
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    private function json(string $method, string $path, array $options = []): array
    {
        $response = $this->request($method, $path, $options);

        try {
            return $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new SourceUnavailableException(\sprintf('Immich injoignable : %s', $e->getMessage()), previous: $e);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        if ('' === $this->apiKey || '' === $this->baseUrl) {
            throw new SourceUnavailableException('Immich non configuré (IMMICH_URL, IMMICH_API_KEY)');
        }
        $options += ['headers' => ['x-api-key' => $this->apiKey], 'timeout' => 5];
        if (null !== $this->caFile && '' !== $this->caFile) {
            $options['cafile'] = $this->caFile;
        }

        try {
            $response = $this->httpClient->request($method, rtrim($this->baseUrl, '/').$path, $options);
            $status = $response->getStatusCode();
            if (401 === $status || 403 === $status) {
                throw new SourceUnavailableException(\sprintf('Clé Immich refusée (%d), vérifier IMMICH_API_KEY et ses permissions (%s)', $status, self::PERMISSIONS));
            }
            if (404 === $status) {
                throw new SourceUnavailableException(\sprintf('Introuvable dans Immich : %s', $path));
            }

            return $response;
        } catch (ExceptionInterface $e) {
            throw new SourceUnavailableException(\sprintf('Immich injoignable : %s', $e->getMessage()), previous: $e);
        }
    }
}
