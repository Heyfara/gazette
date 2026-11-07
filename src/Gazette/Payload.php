<?php

namespace App\Gazette;

use Symfony\Component\HttpFoundation\Request;

/**
 * Lecture et vérification d'un corps JSON : chaque champ lu est vérifié
 * (type, longueur, format) et les refus sont cumulés dans une 422.
 */
final class Payload
{
    public const MONTH = '/^\d{4}-(0[1-9]|1[0-2])$/';

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private readonly array $data)
    {
    }

    public static function fromRequest(Request $request): self
    {
        if ('' === $request->getContent()) {
            return new self([]);
        }
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            throw new InvalidPayloadException(['Corps JSON attendu.']);
        }

        return new self($data);
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    /** Chaîne (absente = null), sans espaces aux bouts, limitée en longueur. */
    public function string(string $key, int $max, bool $trim = true): ?string
    {
        if (!$this->has($key)) {
            return null;
        }
        $value = $this->data[$key];
        if (!\is_string($value)) {
            $this->errors[] = \sprintf('« %s » doit être un texte.', $key);

            return null;
        }
        $value = $trim ? trim($value) : $value;
        if (mb_strlen($value) > $max) {
            $this->errors[] = \sprintf('« %s » dépasse %d caractères.', $key, $max);

            return null;
        }

        return $value;
    }

    public function month(string $key): ?string
    {
        $value = $this->string($key, 7);
        if (null !== $value && !preg_match(self::MONTH, $value)) {
            $this->errors[] = \sprintf('« %s » doit être un mois AAAA-MM.', $key);

            return null;
        }

        return $value;
    }

    /**
     * Liste de chaînes (identifiants).
     *
     * @return list<string>|null
     */
    public function strings(string $key, int $max): ?array
    {
        if (!$this->has($key)) {
            return null;
        }
        $value = $this->data[$key];
        if (!\is_array($value) || array_is_list($value) === false || [] !== array_filter($value, static fn ($v): bool => !\is_string($v))) {
            $this->errors[] = \sprintf('« %s » doit être une liste de textes.', $key);

            return null;
        }
        if (\count($value) > $max) {
            $this->errors[] = \sprintf('« %s » dépasse %d éléments.', $key, $max);

            return null;
        }

        return array_values($value);
    }

    /**
     * Liste d'objets.
     *
     * @return list<array<string, mixed>>|null
     */
    public function objects(string $key, int $max): ?array
    {
        if (!$this->has($key)) {
            return null;
        }
        $value = $this->data[$key];
        if (!\is_array($value) || !array_is_list($value) || [] !== array_filter($value, static fn ($v): bool => !\is_array($v))) {
            $this->errors[] = \sprintf('« %s » doit être une liste d\'objets.', $key);

            return null;
        }
        if (\count($value) > $max) {
            $this->errors[] = \sprintf('« %s » dépasse %d éléments.', $key, $max);

            return null;
        }

        return array_values($value);
    }

    public function error(string $message): void
    {
        $this->errors[] = $message;
    }

    /** Lève la 422 si un champ a été refusé. */
    public function check(): void
    {
        if ([] !== $this->errors) {
            throw new InvalidPayloadException($this->errors);
        }
    }
}
