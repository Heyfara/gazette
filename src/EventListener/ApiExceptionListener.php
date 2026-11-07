<?php

namespace App\EventListener;

use App\Gazette\InvalidPayloadException;
use App\Gazette\IssueNotFoundException;
use App\Gazette\Send\NotSendableException;
use App\Source\SourceUnavailableException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Sous /api, toute erreur répond en JSON { error, details? } : le front n'a
 * jamais de page HTML d'erreur à interpréter.
 */
#[AsEventListener]
final readonly class ApiExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
        /** En debug (dev, tests), la 500 dit quelle exception : en prod, seulement le journal */
        #[Autowire('%kernel.debug%')]
        private bool $debug = false,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $e = $event->getThrowable();
        $event->setResponse(match (true) {
            $e instanceof InvalidPayloadException => new JsonResponse(['error' => 'Données refusées', 'details' => $e->errors], 422),
            $e instanceof IssueNotFoundException => new JsonResponse(['error' => $e->getMessage()], 404),
            $e instanceof NotSendableException => new JsonResponse(['error' => $e->getMessage()], 409),
            $e instanceof SourceUnavailableException => new JsonResponse(['error' => $e->getMessage()], 502),
            $e instanceof HttpExceptionInterface => new JsonResponse(['error' => $e->getMessage()], $e->getStatusCode(), $e->getHeaders()),
            default => $this->internalError($e),
        });
    }

    private function internalError(\Throwable $e): JsonResponse
    {
        $this->logger->error('Erreur API', ['exception' => $e]);

        return new JsonResponse(['error' => 'Erreur interne'] + ($this->debug ? ['details' => [\sprintf('%s : %s (%s:%d)', $e::class, $e->getMessage(), basename($e->getFile()), $e->getLine())]] : []), 500);
    }
}
