<?php

namespace App\Tests\Source\Listmonk;

use App\Source\Listmonk\ListmonkCampaign;
use App\Source\Listmonk\ListmonkClient;
use App\Source\SourceUnavailableException;
use App\Tests\Http\FakeListmonk;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ListmonkClientTest extends TestCase
{
    protected function setUp(): void
    {
        FakeListmonk::reset();
    }

    private function client(?callable $respond = null, string $token = 'jeton'): ListmonkClient
    {
        $respond ??= new FakeListmonk();

        return new ListmonkClient(new MockHttpClient($respond), 'http://listmonk.test', 'gazette', $token);
    }

    private static function campaign(): ListmonkCampaign
    {
        return new ListmonkCampaign('Gazette n° 4', 'La gazette n° 4', [FakeListmonk::LIST_ID], '<p>Coucou</p>', FakeListmonk::TEMPLATE_ID, 'La gazette <gazette@test>', ['Reply-To' => 'famille@test']);
    }

    public function testListsAndTemplatesByName(): void
    {
        $client = $this->client();

        self::assertSame(3, $client->listId('famille'), 'sans tenir compte de la casse');
        self::assertNull($client->listId('Inconnue'));
        self::assertSame(7, $client->templateId('Gazette'));
        self::assertNull($client->templateId('Alerte homelab'), 'un template transactionnel n\'est pas un template de campagne');
        self::assertSame('Authorization: token gazette:jeton', FakeListmonk::$requests[0][2]['headers'][0] ?? FakeListmonk::$requests[0][2]['headers']['Authorization'] ?? null);
    }

    public function testUploadMediaReturnsPublicUrl(): void
    {
        $media = $this->client()->uploadMedia('octets', 'gazette-4-abc.jpg');

        self::assertSame(100, $media->id);
        self::assertSame('https://b2.test/gazette/photo-100.jpg', $media->url);
        $headers = FakeListmonk::$requests[0][2]['headers'];
        self::assertMatchesRegularExpression('#multipart/form-data; boundary=#', implode(' ', \is_array($headers) ? array_map(static fn ($v, $k) => \is_int($k) ? $v : "$k: $v", $headers, array_keys($headers)) : [$headers]));
    }

    public function testCampaignLifecycle(): void
    {
        $client = $this->client();

        $id = $client->createCampaign(self::campaign());
        self::assertSame(40, $id);
        $sent = json_decode(FakeListmonk::$requests[0][2]['body'], true);
        self::assertSame(['Gazette n° 4', 'La gazette n° 4', [3], 'regular', 'html', '<p>Coucou</p>', 7, 'email', ['gazette'], [['Reply-To' => 'famille@test']], 'La gazette <gazette@test>'], array_values($sent));

        $client->updateCampaign($id, self::campaign());
        self::assertSame('draft', $client->campaign($id)->status);
        self::assertStringContainsString('<p>Coucou</p>', $client->preview($id));

        $client->sendTest($id, self::campaign(), ['alice@test']);
        self::assertSame(['alice@test'], json_decode(FakeListmonk::$requests[4][2]['body'], true)['subscribers']);

        $client->start($id);
        self::assertSame('running', $client->campaign($id)->status);
    }

    public function testListmonkErrorsCarryTheirMessage(): void
    {
        $this->expectException(SourceUnavailableException::class);
        $this->expectExceptionMessage('Listmonk a refusé (400) : no subscribers found');

        $this->client()->sendTest($this->client()->createCampaign(self::campaign()), self::campaign(), ['inconnu@test']);
    }

    public function testRefusedToken(): void
    {
        $this->expectExceptionMessage('Jeton Listmonk refusé (401)');
        $this->client(static fn () => new MockResponse('{}', ['http_code' => 401]))->listId('Famille');
    }

    public function testNotConfigured(): void
    {
        self::assertFalse($this->client(token: '')->configured());
        $this->expectExceptionMessage('Listmonk non configuré');
        $this->client(token: '')->listId('Famille');
    }
}
