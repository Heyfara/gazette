<?php

namespace App\Tests\Http;

use Symfony\Contracts\HttpClient\ResponseInterface;

/** Aiguille chaque requête HTTP de test vers le service fictif concerné. Aucun test ne sort sur le réseau. */
final class FakeServices
{
    public function __construct(
        private readonly FakeImmich $immich = new FakeImmich(),
        private readonly FakeListmonk $listmonk = new FakeListmonk(),
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        return match (true) {
            str_starts_with($url, 'http://immich.test') => ($this->immich)($method, $url, $options),
            str_starts_with($url, 'http://listmonk.test') => ($this->listmonk)($method, $url, $options),
            default => throw new \LogicException(\sprintf('Requête hors des services fictifs : %s %s', $method, $url)),
        };
    }
}
