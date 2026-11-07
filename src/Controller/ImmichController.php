<?php

namespace App\Controller;

use App\Source\Immich\ImmichAlbum;
use App\Source\Immich\ImmichClient;
use App\Source\Immich\ImmichPerson;
use App\Source\Immich\ImmichSearch;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/** Le choix des photos : albums, personnes, recherche par période, miniatures relayées. */
final class ImmichController
{
    private const IMAGE_TTL = 86400;

    #[Route('/api/immich/albums', methods: ['GET'])]
    public function albums(ImmichClient $immich): JsonResponse
    {
        return new JsonResponse(array_map(static fn (ImmichAlbum $a): array => $a->toArray(), $immich->albums()));
    }

    #[Route('/api/immich/people', methods: ['GET'])]
    public function people(ImmichClient $immich): JsonResponse
    {
        return new JsonResponse(array_map(static fn (ImmichPerson $p): array => $p->toArray(), $immich->people()));
    }

    /** ?album=<id> ou ?from=AAAA-MM-JJ&to=AAAA-MM-JJ, plus ?person=<id> ; la page suivante avec ?cursor=<nextCursor>. */
    #[Route('/api/immich/search', methods: ['GET'])]
    public function search(Request $request, ImmichClient $immich): JsonResponse
    {
        $album = self::uuid($request, 'album');
        $person = self::uuid($request, 'person');
        $from = self::date($request, 'from');
        $to = self::date($request, 'to');
        if (null === $album && null === $from && null === $to) {
            throw new BadRequestHttpException('Préciser un album (album=) ou une période (from=, to=).');
        }
        $cursor = $request->query->getString('cursor');
        if ('' !== $cursor && !preg_match('/^[A-Za-z0-9_=-]{1,200}$/', $cursor)) {
            throw new BadRequestHttpException('« cursor » invalide.');
        }

        return new JsonResponse($immich->search(new ImmichSearch($album, $from, $to, $person, '' === $cursor ? null : $cursor))->toArray());
    }

    #[Route('/api/immich/assets/{assetId}/{size}', requirements: ['size' => 'thumbnail|preview'], methods: ['GET'])]
    public function thumbnail(string $assetId, string $size, ImmichClient $immich, #[Autowire(service: 'cache.images')] CacheItemPoolInterface $cache): Response
    {
        if (!preg_match(ImmichClient::UUID, $assetId)) {
            throw new NotFoundHttpException('Photo inconnue.');
        }

        return self::cached($cache, \sprintf('immich.asset.%s.%s', $assetId, $size), static function () use ($immich, $assetId, $size): array {
            $image = $immich->thumbnail($assetId, $size);

            return [$image->bytes, $image->contentType];
        });
    }

    #[Route('/api/immich/people/{personId}/thumbnail', methods: ['GET'])]
    public function personThumbnail(string $personId, ImmichClient $immich, #[Autowire(service: 'cache.images')] CacheItemPoolInterface $cache): Response
    {
        if (!preg_match(ImmichClient::UUID, $personId)) {
            throw new NotFoundHttpException('Personne inconnue.');
        }

        return self::cached($cache, \sprintf('immich.person.%s', $personId), static function () use ($immich, $personId): array {
            $image = $immich->personThumbnail($personId);

            return [$image->bytes, $image->contentType];
        });
    }

    /**
     * @param callable(): array{string, string} $load octets et type MIME
     */
    private static function cached(CacheItemPoolInterface $cache, string $key, callable $load): Response
    {
        $item = $cache->getItem($key);
        if (!$item->isHit()) {
            $item->set($load())->expiresAfter(self::IMAGE_TTL);
            $cache->save($item);
        }
        [$bytes, $type] = $item->get();

        return new Response($bytes, 200, [
            'Content-Type' => $type,
            'Cache-Control' => 'private, max-age='.self::IMAGE_TTL,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function uuid(Request $request, string $key): ?string
    {
        $value = $request->query->getString($key);
        if ('' === $value) {
            return null;
        }
        if (!preg_match(ImmichClient::UUID, $value)) {
            throw new BadRequestHttpException(\sprintf('« %s » doit être un identifiant Immich.', $key));
        }

        return $value;
    }

    private static function date(Request $request, string $key): ?\DateTimeImmutable
    {
        $value = $request->query->getString($key);
        if ('' === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('Europe/Paris'));
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException(\sprintf('« %s » doit être une date AAAA-MM-JJ.', $key));
        }

        return $date;
    }
}
