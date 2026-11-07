<?php

namespace App\Source\Listmonk;

/** Une campagne Listmonk telle que l'appli la décrit (création et mise à jour). */
final readonly class ListmonkCampaign
{
    /**
     * @param list<int>            $lists
     * @param array<string,string> $headers en-têtes SMTP (Reply-To…)
     */
    public function __construct(
        public string $name,
        public string $subject,
        public array $lists,
        public string $body,
        public int $templateId,
        public ?string $fromEmail = null,
        public array $headers = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toRequest(): array
    {
        $fields = [
            'name' => $this->name,
            'subject' => $this->subject,
            'lists' => $this->lists,
            'type' => 'regular',
            'content_type' => 'html',
            'body' => $this->body,
            'template_id' => $this->templateId,
            'messenger' => 'email',
            'tags' => ['gazette'],
            'headers' => array_map(static fn (string $k, string $v): array => [$k => $v], array_keys($this->headers), $this->headers),
        ];
        if (null !== $this->fromEmail && '' !== $this->fromEmail) {
            $fields['from_email'] = $this->fromEmail;
        }

        return $fields;
    }
}
