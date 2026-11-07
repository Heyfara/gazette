<?php

namespace App\Gazette\Send;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Les réglages de l'envoi (variables d'environnement, rôle Ansible en prod). */
final readonly class SendConfiguration
{
    /** @var list<string> */
    public array $testEmails;

    public function __construct(
        #[Autowire('%env(LISTMONK_LIST)%')]
        public string $listName,
        #[Autowire('%env(LISTMONK_TEMPLATE)%')]
        public string $templateName,
        #[Autowire('%env(GAZETTE_FROM)%')]
        public string $fromEmail,
        #[Autowire('%env(GAZETTE_REPLY_TO)%')]
        public string $replyTo,
        #[Autowire('%env(GAZETTE_TEST_EMAILS)%')]
        string $testEmails,
    ) {
        $this->testEmails = array_values(array_filter(array_map('trim', explode(',', $testEmails))));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $listmonkConfigured): array
    {
        return [
            'configured' => $listmonkConfigured && '' !== $this->listName && '' !== $this->templateName,
            'list' => $this->listName,
            'template' => $this->templateName,
            'testEmails' => $this->testEmails,
        ];
    }
}
