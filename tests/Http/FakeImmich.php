<?php

namespace App\Tests\Http;

use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Un Immich fictif (http://immich.test, .env.test), branché sur le client HTTP
 * en environnement de test. Aucun test ne sort sur le réseau.
 */
final class FakeImmich
{
    public const P1 = '0a3c1d6e-5b2f-4c8a-9d1e-2f3a4b5c6d71';
    public const P2 = '0a3c1d6e-5b2f-4c8a-9d1e-2f3a4b5c6d72';
    public const P3 = '0a3c1d6e-5b2f-4c8a-9d1e-2f3a4b5c6d73';
    public const ALBUM = '7b1e2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d';
    public const PERSON = '9c8b7a6f-5e4d-4c3b-a291-807f6e5d4c3b';
    public const UNKNOWN = '00000000-0000-4000-8000-000000000000';

    /** @var list<array{string, string, array<string, mixed>}> */
    public static array $requests = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        self::$requests[] = [$method, $url, $options];
        $path = (string) parse_url($url, \PHP_URL_PATH);

        return match (true) {
            '/api/albums' === $path => new JsonMockResponse([
                ['id' => self::ALBUM, 'albumName' => 'Vacances à la mer', 'assetCount' => 2, 'startDate' => '2026-10-15T10:00:00.000Z', 'endDate' => '2026-10-16T12:00:00.000Z', 'albumThumbnailAssetId' => self::P1],
                ['id' => self::UNKNOWN, 'albumName' => 'Vieux album', 'assetCount' => 0, 'startDate' => null, 'endDate' => null, 'albumThumbnailAssetId' => null],
            ]),
            '/api/people' === $path => new JsonMockResponse(['total' => 3, 'people' => [
                ['id' => self::PERSON, 'name' => 'Léo', 'isHidden' => false],
                ['id' => self::UNKNOWN, 'name' => '', 'isHidden' => false],
                ['id' => '9c8b7a6f-5e4d-4c3b-a291-807f6e5d4c3c', 'name' => 'Emma', 'isHidden' => false],
            ]]),
            '/api/search/metadata' === $path => new JsonMockResponse(['albums' => ['items' => []], 'assets' => ['total' => 3, 'count' => 3, 'nextPage' => null, 'nextCursor' => 'eyJvZmZzZXQiOjN9', 'items' => [
                self::asset(self::P1, '2026-10-15T14:30:00.000Z', 'Saint-Malo', ['Léo', 'Emma']),
                self::asset(self::P2, '2026-10-15T16:00:00.000Z', 'Saint-Malo', ['Léo']),
                self::asset(self::P3, '2026-10-16T11:00:00.000Z', 'Cancale', ['Emma', '']),
            ]]]),
            '/api/assets/'.self::P1 === $path => new JsonMockResponse(self::asset(self::P1, '2026-10-15T14:30:00.000Z', 'Saint-Malo', ['Léo', 'Emma'])),
            '/api/assets/'.self::P2 === $path => new JsonMockResponse(self::asset(self::P2, '2026-10-15T16:00:00.000Z', 'Saint-Malo', ['Léo'])),
            '/api/assets/'.self::P3 === $path => new JsonMockResponse(self::asset(self::P3, '2026-10-16T11:00:00.000Z', 'Cancale', ['Emma'])),
            '/api/assets/'.self::UNKNOWN === $path => new MockResponse('{"message":"Not found"}', ['http_code' => 404]),
            1 === preg_match('#^/api/assets/[0-9a-f-]+/thumbnail$#', $path) => new MockResponse(self::jpeg(), ['response_headers' => ['Content-Type' => 'image/jpeg']]),
            1 === preg_match('#^/api/people/[0-9a-f-]+/thumbnail$#', $path) => new MockResponse('octets-visage', ['response_headers' => ['Content-Type' => 'image/jpeg']]),
            default => throw new \LogicException(\sprintf('Requête Immich non prévue par FakeImmich : %s %s', $method, $url)),
        };
    }

    /** Une vraie photo JPEG (1440 × 1080, unie), comme la preview d'Immich : la recompression doit pouvoir la lire. */
    public static function jpeg(): string
    {
        static $bytes = null;
        if (null === $bytes) {
            $image = imagecreatetruecolor(1440, 1080);
            imagefill($image, 0, 0, imagecolorallocate($image, 184, 85, 43));
            ob_start();
            imagejpeg($image, null, 80);
            $bytes = (string) ob_get_clean();
        }

        return $bytes;
    }

    /**
     * @param list<string> $people
     *
     * @return array<string, mixed>
     */
    public static function asset(string $id, string $local, ?string $city, array $people): array
    {
        return [
            'id' => $id,
            'type' => 'IMAGE',
            'localDateTime' => $local,
            'fileCreatedAt' => $local,
            'originalFileName' => 'IMG.HEIC',
            'exifInfo' => ['city' => $city, 'state' => 'Bretagne', 'country' => null === $city ? null : 'France', 'dateTimeOriginal' => $local],
            'people' => array_map(static fn (string $name): array => ['id' => self::PERSON, 'name' => $name], $people),
        ];
    }
}
