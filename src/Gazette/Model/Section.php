<?php

namespace App\Gazette\Model;

/** Une rubrique du numéro : un titre, des photos et le texte final. Les notes sont dans le carnet du numéro. */
final readonly class Section
{
    /**
     * @param list<Photo> $photos
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $text,
        public array $photos,
    ) {
    }

    public static function blank(string $id): self
    {
        return new self($id, '', '', []);
    }

    public function withFields(?string $title = null, ?string $text = null): self
    {
        return new self($this->id, $title ?? $this->title, $text ?? $this->text, $this->photos);
    }

    /**
     * @param list<Photo> $photos
     */
    public function withPhotos(array $photos): self
    {
        return new self($this->id, $this->title, $this->text, $photos);
    }

    public function photo(string $assetId): ?Photo
    {
        foreach ($this->photos as $photo) {
            if ($photo->assetId === $assetId) {
                return $photo;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'text' => $this->text,
            'photos' => array_map(static fn (Photo $p): array => $p->toArray(), $this->photos),
        ];
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        return new self(
            (string) $a['id'],
            (string) ($a['title'] ?? ''),
            (string) ($a['text'] ?? ''),
            array_values(array_map(Photo::fromArray(...), $a['photos'] ?? [])),
        );
    }
}
