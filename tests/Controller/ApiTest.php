<?php

namespace App\Tests\Controller;

use App\Tests\Http\FakeImmich;
use App\Tests\Http\FakeListmonk;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'API telle que le front l'appelle, sur un dossier de données vide
 * (var/test-data, .env.test) et l'Immich fictif de FakeImmich.
 */
final class ApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $dir = \dirname(__DIR__, 2).'/var/test-data';
        foreach (glob($dir.'/*') ?: [] as $f) {
            unlink($f);
        }
        FakeImmich::$requests = [];
        FakeListmonk::reset();
        $this->client = static::createClient();
        // Même noyau d'une requête à l'autre : le cache des miniatures (en mémoire en test) survit.
        $this->client->disableReboot();
        static::getContainer()->get('cache.images')->clear();
    }

    /**
     * @return array<mixed>|null null quand l'API répond « null »
     */
    private function json(string $method, string $url, mixed $body = null, int $expectedStatus = 200, array $headers = []): ?array
    {
        $server = ['HTTP_ACCEPT' => 'application/json'] + $headers;
        if (null !== $body) {
            $server['CONTENT_TYPE'] = 'application/json';
        }
        $this->client->request($method, $url, server: $server, content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));
        $response = $this->client->getResponse();

        self::assertSame($expectedStatus, $response->getStatusCode(), (string) $response->getContent());
        if (204 === $expectedStatus) {
            return [];
        }
        self::assertSame('application/json', $response->headers->get('Content-Type'));

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    public function testConfig(): void
    {
        self::assertSame(['defaultName' => 'La gazette de test', 'maxPhotos' => 12, 'send' => ['configured' => true, 'list' => 'Famille', 'template' => 'Gazette', 'testEmails' => ['alice@test', 'bob@test']]], $this->json('GET', '/api/config'));
    }

    public function testIssueLifecycle(): void
    {
        self::assertSame([], $this->json('GET', '/api/issues'));

        $issue = $this->json('POST', '/api/issues', ['month' => '2026-11', 'title' => 'Spécial rentrée'], 201);
        self::assertSame(1, $issue['number']);
        self::assertSame('La gazette de test', $issue['name'], 'nom par défaut');
        self::assertSame('brouillon', $issue['status']);
        self::assertTrue($issue['editable']);
        self::assertSame([], $issue['sections']);
        $id = $issue['id'];

        $patched = $this->json('PATCH', "/api/issues/$id", ['name' => 'La gazette du village', 'month' => '2026-12']);
        self::assertSame(['La gazette du village', 'Spécial rentrée', '2026-12'], [$patched['name'], $patched['title'], $patched['month']]);

        $list = $this->json('GET', '/api/issues');
        self::assertCount(1, $list);
        self::assertSame(['sections' => 0, 'photos' => 0], ['sections' => $list[0]['sections'], 'photos' => $list[0]['photos']]);
        self::assertArrayNotHasKey('createdAtX', $list[0]);

        $this->json('DELETE', "/api/issues/$id", expectedStatus: 204);
        $this->json('GET', "/api/issues/$id", expectedStatus: 404);
    }

    public function testCreateDefaultsToCurrentMonth(): void
    {
        $issue = $this->json('POST', '/api/issues', [], 201);

        self::assertSame((new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y-m'), $issue['month']);
    }

    public function testRefusedPayloadsListTheirReasons(): void
    {
        $error = $this->json('POST', '/api/issues', ['month' => '2026-13', 'title' => str_repeat('x', 121)], 422);

        self::assertSame('Données refusées', $error['error']);
        self::assertSame(['« title » dépasse 120 caractères.', '« month » doit être un mois AAAA-MM.'], $error['details']);

        $id = $this->json('POST', '/api/issues', [], 201)['id'];
        self::assertSame(['« name » ne peut pas être vide.'], $this->json('PATCH', "/api/issues/$id", ['name' => '  '], 422)['details']);
        self::assertSame(['Corps JSON attendu.'], $this->json('PATCH', "/api/issues/$id", 'pas un objet', 422)['details']);
    }

    public function testSectionsPhotosPromptAndPreview(): void
    {
        $id = $this->json('POST', '/api/issues', ['month' => '2026-11'], 201)['id'];

        $issue = $this->json('POST', "/api/issues/$id/sections", ['title' => 'À la mer'], 201);
        $issue = $this->json('POST', "/api/issues/$id/sections", [], 201);
        self::assertSame(['À la mer', ''], array_column($issue['sections'], 'title'));
        [$s1, $s2] = array_column($issue['sections'], 'id');

        $issue = $this->json('PATCH', "/api/issues/$id/sections/$s1", [
            'notes' => "première fois à la mer\n",
            'text' => "Léo a adoré.\n\nEmma aussi.",
            'photos' => [['assetId' => FakeImmich::P2, 'caption' => 'Léo face aux vagues'], ['assetId' => FakeImmich::P1, 'caption' => ''], ['assetId' => FakeImmich::P2, 'caption' => 'doublon ignoré']],
        ]);
        $section = $issue['sections'][0];
        self::assertSame("première fois à la mer\n", $section['notes'], 'les notes gardent leurs sauts de ligne');
        self::assertSame([FakeImmich::P2, FakeImmich::P1], array_column($section['photos'], 'assetId'));
        self::assertSame([
            'assetId' => FakeImmich::P2, 'takenAt' => '2026-10-15T16:00:00', 'city' => 'Saint-Malo', 'country' => 'France', 'people' => ['Léo'], 'caption' => 'Léo face aux vagues', 'mediaUrl' => null, 'mediaId' => null,
        ], $section['photos'][0], 'métadonnées copiées d\'Immich');
        self::assertCount(2, array_filter(FakeImmich::$requests, static fn (array $r): bool => str_contains($r[1], '/api/assets/')), 'une lecture Immich par nouvelle photo');

        FakeImmich::$requests = [];
        FakeListmonk::reset();
        $issue = $this->json('PATCH', "/api/issues/$id/sections/$s1", ['photos' => [['assetId' => FakeImmich::P1, 'caption' => 'Emma']]]);
        self::assertSame([['Emma']], array_map(static fn (array $p): array => [$p['caption']], $issue['sections'][0]['photos']));
        self::assertSame([], FakeImmich::$requests, 'photo déjà connue : pas de relecture');

        $prompt = $this->json('GET', "/api/issues/$id/sections/$s1/prompt")['prompt'];
        self::assertStringContainsString('1. 15/10/2026, Saint-Malo (France), personnes : Léo, Emma, légende : Emma', $prompt);
        self::assertStringContainsString('(notes de l\'auteur) : première fois à la mer', $prompt);
        self::assertStringContainsString('La famille : Alice (la maman)', $prompt);

        $issue = $this->json('PUT', "/api/issues/$id/sections-order", ['order' => [$s2, $s1]]);
        self::assertSame([$s2, $s1], array_column($issue['sections'], 'id'));
        self::assertSame(['L\'ordre doit reprendre chaque section une fois, sans autre.'], $this->json('PUT', "/api/issues/$id/sections-order", ['order' => [$s1]], 422)['details']);

        $this->client->request('GET', "/api/issues/$id/preview");
        $html = (string) $this->client->getResponse()->getContent();
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringStartsWith('text/html', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('src="/api/immich/assets/'.FakeImmich::P1.'/preview"', $html);
        self::assertStringContainsString('Emma aussi.', $html);

        $issue = $this->json('DELETE', "/api/issues/$id/sections/$s2");
        self::assertSame([$s1], array_column($issue['sections'], 'id'));
        $this->json('DELETE', "/api/issues/$id/sections/inconnue", expectedStatus: 404);
        $this->json('GET', "/api/issues/$id/sections/inconnue/prompt", expectedStatus: 404);
    }

    public function testPhotosAreCheckedBeforeAskingImmich(): void
    {
        $id = $this->json('POST', '/api/issues', [], 201)['id'];
        $s = $this->json('POST', "/api/issues/$id/sections", [], 201)['sections'][0]['id'];

        $error = $this->json('PATCH', "/api/issues/$id/sections/$s", ['photos' => [['assetId' => 'nope', 'caption' => ''], ['assetId' => FakeImmich::P1, 'caption' => str_repeat('x', 201)]]], 422);
        self::assertSame(['Photo 1 : identifiant Immich invalide.', 'Photo 2 : légende absente ou trop longue (200 caractères).'], $error['details']);

        $error = $this->json('PATCH', "/api/issues/$id/sections/$s", ['photos' => [['assetId' => FakeImmich::UNKNOWN, 'caption' => '']]], 502);
        self::assertStringContainsString('Introuvable dans Immich', $error['error']);
        self::assertSame([], $this->json('GET', "/api/issues/$id")['sections'][0]['photos'], 'rien d\'enregistré');
    }

    public function testImmichBrowsing(): void
    {
        $albums = $this->json('GET', '/api/immich/albums');
        self::assertSame('Vacances à la mer', $albums[0]['name']);

        self::assertSame(['Emma', 'Léo'], array_column($this->json('GET', '/api/immich/people'), 'name'));

        $page = $this->json('GET', '/api/immich/search?album='.FakeImmich::ALBUM.'&person='.FakeImmich::PERSON);
        self::assertSame(3, $page['count']);
        self::assertSame('eyJvZmZzZXQiOjN9', $page['nextCursor']);
        self::assertSame(['Léo', 'Emma'], $page['assets'][0]['people']);

        $this->json('GET', '/api/immich/search?from=2026-10-01&to=2026-10-31&cursor=eyJvZmZzZXQiOjN9');
        $this->json('GET', '/api/immich/search?from=2026-10-01&cursor=pas%20valide', expectedStatus: 400);
        $this->json('GET', '/api/immich/search', expectedStatus: 400);
        self::assertSame('« from » doit être une date AAAA-MM-JJ.', $this->json('GET', '/api/immich/search?from=2026-13-01', expectedStatus: 400)['error']);
        $this->json('GET', '/api/immich/search?album=x', expectedStatus: 400);
    }

    public function testThumbnailsAreRelayedAndCached(): void
    {
        $this->client->request('GET', '/api/immich/assets/'.FakeImmich::P1.'/preview');
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        self::assertSame(FakeImmich::jpeg(), $response->getContent());

        $this->client->request('GET', '/api/immich/assets/'.FakeImmich::P1.'/preview');
        self::assertCount(1, FakeImmich::$requests, 'deuxième lecture servie par le cache');

        $this->client->request('GET', '/api/immich/people/'.FakeImmich::PERSON.'/thumbnail');
        self::assertSame('octets-visage', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/immich/assets/pas-un-uuid/preview');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        $this->client->request('GET', '/api/immich/assets/'.FakeImmich::P1.'/enorme');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testWritesFromAnotherSiteAreRefused(): void
    {
        $this->json('POST', '/api/issues', [], 403, ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        $this->json('POST', '/api/issues', [], 403, ['HTTP_ORIGIN' => 'https://ailleurs.test']);
        $this->json('POST', '/api/issues', [], 201, ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'http://localhost']);

        $this->client->request('POST', '/api/issues', server: ['CONTENT_TYPE' => 'text/plain'], content: 'x');
        self::assertSame(415, $this->client->getResponse()->getStatusCode());
    }

    public function testErrorsAreJson(): void
    {
        self::assertSame(['error' => 'Numéro inconnu : deadbeef'], $this->json('GET', '/api/issues/deadbeef', expectedStatus: 404));
        $this->client->request('GET', '/api/nulle-part');
        self::assertSame('application/json', $this->client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * @return array{string, string} id du numéro, id de la rubrique
     */
    private function issueWithPhotos(): array
    {
        $id = $this->json('POST', '/api/issues', ['month' => '2026-11', 'title' => 'Spécial mer'], 201)['id'];
        $s = $this->json('POST', "/api/issues/$id/sections", ['title' => 'À la mer'], 201)['sections'][0]['id'];
        $this->json('PATCH', "/api/issues/$id/sections/$s", ['text' => 'Léo a adoré.', 'photos' => [['assetId' => FakeImmich::P1, 'caption' => 'Léo'], ['assetId' => FakeImmich::P2, 'caption' => '']]]);
        FakeImmich::$requests = [];

        return [$id, $s];
    }

    public function testPrepareUploadsPhotosOnceAndCreatesTheCampaign(): void
    {
        [$id, $s] = $this->issueWithPhotos();

        $issue = $this->json('POST', "/api/issues/$id/prepare");

        self::assertSame(40, $issue['campaignId']);
        self::assertSame('brouillon', $issue['status']);
        self::assertSame(['https://b2.test/gazette/photo-100.jpg', 'https://b2.test/gazette/photo-101.jpg'], array_column($issue['sections'][0]['photos'], 'mediaUrl'));
        self::assertCount(2, array_filter(FakeImmich::$requests, static fn (array $r): bool => str_contains($r[1], '/thumbnail')), 'preview Immich lue pour chaque photo');
        $campaign = FakeListmonk::$campaigns[40];
        self::assertSame('Gazette n° 1 (novembre 2026)', $campaign['name']);
        self::assertSame('La gazette de test n° 1 : Spécial mer', $campaign['subject']);
        self::assertSame([FakeListmonk::LIST_ID], $campaign['lists']);
        self::assertSame(FakeListmonk::TEMPLATE_ID, $campaign['template_id']);
        self::assertSame('La gazette <gazette@test>', $campaign['from_email']);
        self::assertSame([['Reply-To' => 'famille@test']], $campaign['headers']);
        self::assertStringContainsString('<img src="https://b2.test/gazette/photo-100.jpg" alt="Léo"', $campaign['body']);
        self::assertStringContainsString('Léo a adoré.', $campaign['body']);
        self::assertMatchesRegularExpression('/filename="gazette-1-[0-9a-f]{12}\\.jpg"/', FakeListmonk::$lastUpload, 'nom de fichier non devinable');
        self::assertStringContainsString('Content-Type: image/jpeg', FakeListmonk::$lastUpload);

        // Deuxième préparation après une retouche : pas de nouveau dépôt, campagne mise à jour.
        FakeImmich::$requests = [];
        FakeListmonk::$requests = [];
        $this->json('PATCH', "/api/issues/$id/sections/$s", ['text' => 'Léo a adoré, Emma aussi.']);
        $issue = $this->json('POST', "/api/issues/$id/prepare");
        self::assertSame(40, $issue['campaignId']);
        self::assertSame([], FakeImmich::$requests);
        self::assertSame(['GET', 'GET', 'PUT'], array_map(static fn (array $r): string => $r[0], FakeListmonk::$requests), 'liste, template, mise à jour');
        self::assertStringContainsString('Emma aussi.', FakeListmonk::$campaigns[40]['body']);

        $this->client->request('GET', "/api/issues/$id/mail-preview");
        self::assertStringContainsString('Bonjour Alice,', (string) $this->client->getResponse()->getContent());
    }

    public function testTestThenSend(): void
    {
        [$id] = $this->issueWithPhotos();

        self::assertSame('Envoyer d\'abord un test, et le relire, avant l\'envoi à la famille.', $this->json('POST', "/api/issues/$id/send", expectedStatus: 409)['error']);

        $issue = $this->json('POST', "/api/issues/$id/test");
        self::assertSame('teste', $issue['status']);
        self::assertTrue($issue['editable'], 'on peut encore corriger après un test');
        $test = array_values(array_filter(FakeListmonk::$requests, static fn (array $r): bool => str_ends_with($r[1], '/test')))[0];
        self::assertSame(['alice@test', 'bob@test'], json_decode($test[2]['body'], true)['subscribers']);

        $issue = $this->json('POST', "/api/issues/$id/send");
        self::assertSame('envoye', $issue['status']);
        self::assertFalse($issue['editable']);
        self::assertNotNull($issue['sentAt']);
        self::assertSame('running', FakeListmonk::$campaigns[40]['status']);
        self::assertSame(['id' => 40, 'status' => 'running', 'toSend' => 5, 'sent' => 0], $this->json('GET', "/api/issues/$id/campaign"));

        self::assertSame('Ce numéro a déjà été envoyé.', $this->json('POST', "/api/issues/$id/prepare", expectedStatus: 409)['error']);
        $this->json('PATCH', "/api/issues/$id", ['title' => 'x'], 409);
        $this->json('DELETE', "/api/issues/$id", expectedStatus: 409);
    }

    public function testNothingToSend(): void
    {
        $id = $this->json('POST', '/api/issues', [], 201)['id'];
        self::assertSame('Le numéro n\'a aucune rubrique.', $this->json('POST', "/api/issues/$id/prepare", expectedStatus: 409)['error']);

        $this->json('POST', "/api/issues/$id/sections", ['title' => 'Vide'], 201);
        self::assertSame('La rubrique « Vide » est vide : ni texte ni photo.', $this->json('POST', "/api/issues/$id/prepare", expectedStatus: 409)['error']);
        self::assertNull($this->json('GET', "/api/issues/$id/campaign"));
    }

    public function testMissingListIsExplained(): void
    {
        [$id] = $this->issueWithPhotos();
        FakeListmonk::$noList = true;

        $error = $this->json('POST', "/api/issues/$id/prepare", expectedStatus: 409)['error'];

        self::assertStringContainsString('Liste Listmonk introuvable : « Famille »', $error);
        self::assertSame([], array_filter(FakeListmonk::$requests, static fn (array $r): bool => str_ends_with($r[1], '/api/media')), 'rien déposé');
    }
}
