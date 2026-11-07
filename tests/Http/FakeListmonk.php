<?php

namespace App\Tests\Http;

use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Un Listmonk fictif (http://listmonk.test, .env.test) : une liste « Famille »,
 * un template « Gazette », une médiathèque et des campagnes en mémoire.
 */
final class FakeListmonk
{
    public const LIST_ID = 3;
    public const TEMPLATE_ID = 7;

    /** @var list<array{string, string, array<string, mixed>}> */
    public static array $requests = [];
    /** @var array<int, array<string, mixed>> campagnes créées, par id */
    public static array $campaigns = [];
    public static int $nextMedia = 100;
    public static int $nextCampaign = 40;
    /** Si vrai, la liste « Famille » n'existe pas */
    public static bool $noList = false;
    /** Corps multipart du dernier dépôt de média (lu ici : le client mock ne l'envoie nulle part) */
    public static string $lastUpload = '';

    public static function reset(): void
    {
        self::$requests = [];
        self::$campaigns = [];
        self::$nextMedia = 100;
        self::$nextCampaign = 40;
        self::$noList = false;
        self::$lastUpload = '';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        self::$requests[] = [$method, $url, $options];
        $path = (string) parse_url($url, \PHP_URL_PATH);
        $body = isset($options['body']) && \is_string($options['body']) ? json_decode($options['body'], true) : null;
        if ('/api/media' === $path && isset($options['body']) && \is_callable($options['body'])) {
            self::$lastUpload = '';
            while ('' !== ($chunk = $options['body'](8192))) {
                self::$lastUpload .= $chunk;
            }
        }
        $data = static fn (mixed $d): JsonMockResponse => new JsonMockResponse(['data' => $d]);

        if (preg_match('#^/api/campaigns/(\d+)(/(\w+))?$#', $path, $m)) {
            $id = (int) $m[1];
            $action = $m[3] ?? '';
            if (!isset(self::$campaigns[$id])) {
                return new JsonMockResponse(['message' => 'campaign not found'], ['http_code' => 400]);
            }

            return match ([$method, $action]) {
                ['GET', ''] => $data(self::$campaigns[$id]),
                ['PUT', ''] => $data(self::$campaigns[$id] = ['id' => $id, 'status' => self::$campaigns[$id]['status'], 'to_send' => 0, 'sent' => 0] + (array) $body),
                ['GET', 'preview'] => new MockResponse('<html><body>Bonjour Alice,'.self::$campaigns[$id]['body'].'</body></html>', ['response_headers' => ['Content-Type' => 'text/html']]),
                ['POST', 'test'] => [] === ($body['subscribers'] ?? []) || \in_array('inconnu@test', $body['subscribers'] ?? [], true)
                    ? new JsonMockResponse(['message' => 'no subscribers found'], ['http_code' => 400])
                    : $data(true),
                ['PUT', 'status'] => 'running' === ($body['status'] ?? '') && 'draft' === self::$campaigns[$id]['status']
                    ? $data(self::$campaigns[$id] = ['status' => 'running', 'to_send' => 5] + self::$campaigns[$id])
                    : new JsonMockResponse(['message' => 'invalid status transition'], ['http_code' => 400]),
                default => throw new \LogicException(\sprintf('Requête Listmonk non prévue : %s %s', $method, $url)),
            };
        }

        return match ([$method, $path]) {
            ['GET', '/api/lists'] => $data(['results' => self::$noList ? [] : [['id' => 1, 'name' => 'Alertes'], ['id' => self::LIST_ID, 'name' => 'Famille']]]),
            ['POST', '/api/lists'] => $data(['id' => 9] + (array) $body),
            ['GET', '/api/templates'] => $data([['id' => 1, 'name' => 'Default campaign template', 'type' => 'campaign'], ['id' => 5, 'name' => 'Alerte homelab', 'type' => 'tx'], ['id' => self::TEMPLATE_ID, 'name' => 'Gazette', 'type' => 'campaign']]),
            ['POST', '/api/templates'] => $data(['id' => 11] + (array) $body),
            ['POST', '/api/media'] => $data(['id' => self::$nextMedia, 'uuid' => 'u', 'filename' => 'photo.jpg', 'url' => \sprintf('https://b2.test/gazette/photo-%d.jpg', self::$nextMedia++), 'thumb_url' => 'https://b2.test/thumb.jpg']),
            ['POST', '/api/campaigns'] => $data(self::$campaigns[self::$nextCampaign] = ['id' => self::$nextCampaign++, 'status' => 'draft', 'to_send' => 0, 'sent' => 0] + (array) $body),
            default => throw new \LogicException(\sprintf('Requête Listmonk non prévue : %s %s', $method, $url)),
        };
    }
}
