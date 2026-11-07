<?php

namespace App\Gazette;

use App\Gazette\Model\Photo;
use App\Gazette\Model\Section;
use App\Source\Immich\ImmichClient;

/**
 * La liste des photos d'une rubrique telle que le front l'envoie
 * ([{assetId, caption}] dans l'ordre voulu) : les photos déjà connues gardent
 * leurs métadonnées, les nouvelles sont lues dans Immich.
 */
final readonly class SectionPhotos
{
    public const MAX = 30;

    public function __construct(
        private ImmichClient $immich,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $wanted
     *
     * @return list<Photo>
     */
    public function resolve(Section $section, array $wanted): array
    {
        $errors = [];
        $photos = [];
        $seen = [];
        foreach ($wanted as $i => $item) {
            $assetId = $item['assetId'] ?? null;
            $caption = $item['caption'] ?? '';
            if (!\is_string($assetId) || !preg_match(ImmichClient::UUID, $assetId)) {
                $errors[] = \sprintf('Photo %d : identifiant Immich invalide.', $i + 1);
                continue;
            }
            if (!\is_string($caption) || mb_strlen($caption) > 200) {
                $errors[] = \sprintf('Photo %d : légende absente ou trop longue (200 caractères).', $i + 1);
                continue;
            }
            if (isset($seen[$assetId])) {
                continue;
            }
            $seen[$assetId] = true;
            $known = $section->photo($assetId);
            if (null !== $known) {
                $photos[] = $known->withCaption(trim($caption));
                continue;
            }
            $asset = $this->immich->asset($assetId);
            $photos[] = new Photo($asset->id, $asset->takenAt, $asset->city, $asset->country, $asset->people, trim($caption));
        }
        if ([] !== $errors) {
            throw new InvalidPayloadException($errors);
        }

        return $photos;
    }
}
