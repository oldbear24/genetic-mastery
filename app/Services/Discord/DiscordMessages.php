<?php

namespace App\Services\Discord;

use App\Models\Submission;

/**
 * Discord message payloads (DC-1 to DC-3). Texts are Czech, mentions are
 * limited by allowed_mentions: only the discoverer may be pinged, never
 * "everyone", "here" or roles (DC-2).
 */
class DiscordMessages
{
    public const COLOR_VERIFIED = 0x45D19E;

    /**
     * DC-1: NOVÝ GENETICKÝ MILNÍK.
     *
     * @param  array{verified: int, total: int, indo: int, indo_max: int}  $counts
     * @return array<string, mixed>
     */
    public function announcement(Submission $submission, array $counts, string $nonce): array
    {
        $milestone = $submission->milestone;
        $player = $submission->user;
        $url = route('milestones.show', $milestone);

        return [
            'content' => __('discord.announce.content', ['mention' => "<@{$player->discord_id}>"]),
            'embeds' => [[
                'title' => __('discord.announce.title'),
                'url' => $url,
                'color' => self::COLOR_VERIFIED,
                'fields' => [
                    ['name' => __('discord.announce.species'), 'value' => $milestone->dinosaurName(), 'inline' => true],
                    ['name' => __('discord.announce.stat'), 'value' => $milestone->statName(), 'inline' => true],
                    ['name' => __('discord.announce.value'), 'value' => (string) $milestone->required_value, 'inline' => true],
                    ['name' => __('discord.announce.discoverer'), 'value' => $this->plain($player->display_name), 'inline' => true],
                    ['name' => __('discord.announce.progress'), 'value' => "{$counts['verified']} / {$counts['total']}", 'inline' => true],
                    ['name' => __('discord.announce.indo'), 'value' => "{$counts['indo']} / {$counts['indo_max']}", 'inline' => true],
                ],
                'description' => '['.__('discord.announce.link').']('.$url.')',
            ]],
            'allowed_mentions' => ['parse' => [], 'users' => [$player->discord_id]],
            'nonce' => $nonce,
            'enforce_nonce' => true,
        ];
    }

    /**
     * DC-3: the original announcement edited in place.
     *
     * @return array<string, mixed>
     */
    public function revokedEdit(Submission $submission): array
    {
        return [
            'content' => __('discord.revoked.edit', ['milestone' => $submission->milestone->title()]),
            'embeds' => [],
            'allowed_mentions' => ['parse' => []],
        ];
    }

    /**
     * DC-3: a short correction with the new numbers, as a reply to the
     * original message.
     *
     * @param  array{verified: int, total: int, indo: int, indo_max: int}  $counts
     * @return array<string, mixed>
     */
    public function correction(Submission $submission, array $counts, ?string $originalMessageId, string $nonce): array
    {
        $payload = [
            'content' => __('discord.revoked.correction', [
                'milestone' => $submission->milestone->title(),
                'verified' => $counts['verified'],
                'total' => $counts['total'],
                'indo' => $counts['indo'],
                'indo_max' => $counts['indo_max'],
            ]),
            'allowed_mentions' => ['parse' => []],
            'nonce' => $nonce,
            'enforce_nonce' => true,
        ];

        if ($originalMessageId !== null) {
            $payload['message_reference'] = ['message_id' => $originalMessageId, 'fail_if_not_exists' => false];
        }

        return $payload;
    }

    /** Names are shown as typed, without Markdown or mentions sneaking in. */
    public function plain(string $text): string
    {
        $escaped = preg_replace('/([\\\\*_~`|>#\[\]()-])/u', '\\\\$1', $text) ?? $text;

        return str_replace('@', "@\u{200B}", $escaped);
    }
}
