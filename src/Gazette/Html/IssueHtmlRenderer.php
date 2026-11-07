<?php

namespace App\Gazette\Html;

use App\Gazette\Model\Issue;
use App\Gazette\Model\Photo;
use App\Gazette\Model\Section;
use App\Gazette\Prompt\PromptBuilder;

/**
 * Le corps HTML d'un numéro : un bloc par rubrique (titre, photos, texte), en
 * tableaux et styles en ligne pour Mail iOS et Gmail. C'est ce que recevra
 * le template Listmonk ; l'aperçu l'habille d'un cadre de 600 px.
 */
final readonly class IssueHtmlRenderer
{
    /**
     * @param callable(Photo): string $imageUrl adresse de chaque photo dans le mail
     */
    public function body(Issue $issue, callable $imageUrl): string
    {
        return implode("\n", array_map(fn (Section $s): string => $this->section($s, $imageUrl), $issue->sections));
    }

    /**
     * Page complète pour l'aperçu dans l'appli : en-tête, corps, pied, 600 px de large.
     *
     * @param callable(Photo): string $imageUrl
     */
    public function preview(Issue $issue, callable $imageUrl): string
    {
        $name = self::e($issue->name);
        $title = '' === $issue->title ? '' : \sprintf('<p style="margin:8px 0 0;font-size:18px;color:#444">%s</p>', self::e($issue->title));

        return <<<HTML
            <!doctype html>
            <html lang="fr">
            <head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>{$name}</title></head>
            <body style="margin:0;padding:16px;background:#f3f1ec;font-family:Georgia,'Times New Roman',serif;color:#222">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#fffdf8;border:1px solid #e6e1d6">
            <tr><td style="padding:28px 32px 16px;border-bottom:2px solid #b8552b;text-align:center">
            <p style="margin:0;font-size:13px;letter-spacing:2px;text-transform:uppercase;color:#b8552b">Numéro {$issue->number} · {$this->e(PromptBuilder::month($issue->month))}</p>
            <h1 style="margin:8px 0 0;font-size:30px;font-weight:normal">{$name}</h1>{$title}
            </td></tr>
            <tr><td style="padding:8px 32px 24px">
            {$this->body($issue, $imageUrl)}
            </td></tr>
            <tr><td style="padding:16px 32px;border-top:1px solid #e6e1d6;font-size:13px;color:#777;text-align:center">Répondez à ce mail pour nous écrire.</td></tr>
            </table>
            </td></tr></table>
            </body>
            </html>
            HTML;
    }

    /**
     * @param callable(Photo): string $imageUrl
     */
    private function section(Section $section, callable $imageUrl): string
    {
        $title = '' === $section->title ? '' : \sprintf('<h2 style="margin:24px 0 12px;font-size:22px;font-weight:normal;color:#b8552b">%s</h2>', self::e($section->title));
        $photos = implode('', array_map(static function (Photo $p) use ($imageUrl): string {
            $alt = '' === $p->caption ? 'Photo' : $p->caption;
            $caption = '' === $p->caption ? '' : \sprintf('<p style="margin:6px 0 0;font-size:14px;color:#666;text-align:center">%s</p>', self::e($p->caption));

            return \sprintf(
                '<div style="margin:0 0 16px"><img src="%s" alt="%s" width="536" style="display:block;width:100%%;max-width:536px;height:auto;border-radius:6px;margin:0 auto">%s</div>',
                self::e($imageUrl($p)),
                self::e($alt),
                $caption,
            );
        }, $section->photos));
        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $section->text) ?: []), static fn (string $p): bool => '' !== $p));
        $text = implode('', array_map(static fn (string $p): string => \sprintf('<p style="margin:0 0 14px;font-size:17px;line-height:1.55">%s</p>', nl2br(self::e($p))), $paragraphs));

        return \sprintf('<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0"><tr><td>%s%s%s</td></tr></table>', $title, $photos, $text);
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
