<?php

namespace App\Tests\Source\Immich;

use App\Source\Immich\ImmichAsset;
use App\Source\Immich\ImmichClient;
use App\Source\Immich\ImmichPerson;
use App\Source\Immich\ImmichSearch;
use App\Source\SourceUnavailableException;
use App\Tests\Http\FakeImmich;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ImmichClientTest extends TestCase
{
    /** @var list<array{string, string, array<string, mixed>}> */
    private array $requests = [];

    private function client(?callable $respond = null, string $key = 'cle', string $url = 'http://immich.test'): ImmichClient
    {
        $respond ??= new FakeImmich();

        return new ImmichClient(new MockHttpClient(function (string $method, string $url, array $options) use ($respond) {
            $this->requests[] = [$method, $url, $options];

            return $respond($method, $url, $options);
        }), $url, $key, '/certs/ca.pem');
    }

    public function testAlbumsMostRecentFirst(): void
    {
        $albums = $this->client()->albums();

        self::assertSame(['Vacances à la mer', 'Vieux album'], array_map(static fn ($a) => $a->name, $albums));
        self::assertSame(['id' => FakeImmich::ALBUM, 'name' => 'Vacances à la mer', 'count' => 2, 'start' => '2026-10-15', 'end' => '2026-10-16', 'coverAssetId' => FakeImmich::P1], $albums[0]->toArray());
        self::assertContains('x-api-key: cle', $this->requests[0][2]['headers']);
        self::assertSame('/certs/ca.pem', $this->requests[0][2]['cafile']);
    }

    public function testSearchByAlbumPeriodAndPerson(): void
    {
        $page = $this->client()->search(new ImmichSearch(FakeImmich::ALBUM, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-31'), FakeImmich::PERSON, page: 2));

        self::assertSame(3, $page->total);
        self::assertNull($page->nextPage);
        self::assertEquals(new ImmichAsset(FakeImmich::P1, new \DateTimeImmutable('2026-10-15T14:30:00'), 'Saint-Malo', 'France', ['Léo', 'Emma']), $page->assets[0]);
        self::assertSame(['Emma'], $page->assets[2]->people, 'les visages sans nom sont ignorés');

        [$method, , $options] = $this->requests[0];
        self::assertSame('POST', $method);
        $body = json_decode($options['body'], true);
        self::assertSame(2, $body['page']);
        self::assertTrue($body['withExif']);
        self::assertSame([
            'visibility' => ['eq' => 'timeline'],
            'type' => ['eq' => 'IMAGE'],
            'albumIds' => ['any' => [FakeImmich::ALBUM]],
            'takenAt' => ['gte' => '2026-10-01T00:00:00+00:00', 'lte' => '2026-10-31T23:59:59+00:00'],
            'personIds' => ['all' => [FakeImmich::PERSON]],
        ], $body['filter']);
    }

    public function testSearchByPeriodOnly(): void
    {
        $this->client()->search(new ImmichSearch(from: new \DateTimeImmutable('2026-10-01')));

        $filter = json_decode($this->requests[0][2]['body'], true)['filter'];
        self::assertSame(['gte' => '2026-10-01T00:00:00+00:00'], $filter['takenAt']);
        self::assertArrayNotHasKey('albumIds', $filter);
        self::assertArrayNotHasKey('personIds', $filter);
    }

    public function testNamedPeopleSorted(): void
    {
        self::assertEquals([new ImmichPerson('9c8b7a6f-5e4d-4c3b-a291-807f6e5d4c3c', 'Emma'), new ImmichPerson(FakeImmich::PERSON, 'Léo')], $this->client()->people());
    }

    public function testAssetAndThumbnails(): void
    {
        $client = $this->client();

        self::assertSame('Cancale', $client->asset(FakeImmich::P3)->city);
        self::assertSame('image/webp', $client->thumbnail(FakeImmich::P1, 'preview')->contentType);
        parse_str((string) parse_url($this->requests[1][1], \PHP_URL_QUERY), $query);
        self::assertSame(['size' => 'preview'], $query);
        self::assertSame(15.0, (float) $this->requests[1][2]['timeout']);
        self::assertSame('octets-visage', $client->personThumbnail(FakeImmich::PERSON)->bytes);
    }

    public function testRejectsBadIdsBeforeCalling(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client()->asset('../server/config');
    }

    public function testUnknownAssetIs404(): void
    {
        $this->expectException(SourceUnavailableException::class);
        $this->expectExceptionMessage('Introuvable dans Immich');
        $this->client()->asset(FakeImmich::UNKNOWN);
    }

    public function testRefusedKeyNamesThePermissions(): void
    {
        $this->expectExceptionMessage('Clé Immich refusée (403)');
        $this->client(static fn () => new MockResponse('{}', ['http_code' => 403]))->albums();
    }

    public function testMissingConfiguration(): void
    {
        $this->expectExceptionMessage('IMMICH_URL');
        $this->client(key: '')->albums();
    }

    public function testUnreachable(): void
    {
        $this->expectExceptionMessage('Immich injoignable');
        $this->client(static fn () => new MockResponse('', ['error' => 'Connexion refusée']))->albums();
    }
}
