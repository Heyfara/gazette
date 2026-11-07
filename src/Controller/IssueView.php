<?php

namespace App\Controller;

use App\Gazette\Model\Issue;
use App\Gazette\Model\Section;

/** Le numéro tel que le front le lit (assets/http/types.ts : IssueView, IssueSummary). */
final class IssueView
{
    /**
     * @return array<string, mixed>
     */
    public static function full(Issue $issue): array
    {
        $view = self::summary($issue);
        $view['sections'] = array_map(static fn (Section $s): array => $s->toArray(), $issue->sections);

        return $view;
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Issue $issue): array
    {
        return [
            'id' => $issue->id,
            'number' => $issue->number,
            'name' => $issue->name,
            'title' => $issue->title,
            'month' => $issue->month,
            'status' => $issue->status->value,
            'editable' => $issue->status->editable(),
            'sections' => \count($issue->sections),
            'photos' => \count($issue->photos()),
            'createdAt' => $issue->createdAt->format(\DateTimeInterface::ATOM),
            'sentAt' => $issue->sentAt?->format(\DateTimeInterface::ATOM),
            'campaignId' => $issue->listmonkCampaignId,
        ];
    }
}
