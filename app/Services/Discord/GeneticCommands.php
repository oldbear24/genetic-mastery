<?php

namespace App\Services\Discord;

use App\Enums\MilestoneState;
use App\Models\Milestone;
use App\Models\Species;
use App\Services\ClusterProgress;

/**
 * The /genetic slash command: its definition for registration and the
 * answers (kap. Discord integrace). Command names stay English, texts are
 * Czech. Every answer forbids mentions.
 */
class GeneticCommands
{
    public const EPHEMERAL = 64;

    public function __construct(private readonly ClusterProgress $progress) {}

    /**
     * Species choices come from the active catalogue (Discord allows at most
     * 25 choices). CommandSync re-registers the command when they change.
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        $species = Species::query()->active()->ordered()->limit(25)->get()
            ->map(fn (Species $s) => ['name' => $s->name, 'value' => $s->code])->values()->all();
        $stats = collect(config('genetic.stats'))->map(fn (string $name, string $code) => ['name' => $name, 'value' => $code])->values()->all();

        return [[
            'name' => 'genetic',
            'type' => 1,
            'description' => __('discord.commands.genetic'),
            'options' => [
                ['type' => 1, 'name' => 'overview', 'description' => __('discord.commands.overview')],
                ['type' => 1, 'name' => 'progress', 'description' => __('discord.commands.progress'), 'options' => [
                    ['type' => 3, 'name' => 'druh', 'description' => __('discord.commands.progress_species'), 'required' => false, 'choices' => $species],
                ]],
                ['type' => 1, 'name' => 'leaderboard', 'description' => __('discord.commands.leaderboard')],
                ['type' => 1, 'name' => 'milestone', 'description' => __('discord.commands.milestone'), 'options' => [
                    ['type' => 3, 'name' => 'druh', 'description' => __('discord.commands.milestone_species'), 'required' => true, 'choices' => $species],
                    ['type' => 3, 'name' => 'stat', 'description' => __('discord.commands.milestone_stat'), 'required' => true, 'choices' => $stats],
                ]],
            ],
        ]];
    }

    /**
     * Interaction response (type 4) for an APPLICATION_COMMAND interaction.
     *
     * @param  array<string, mixed>  $data  interaction "data"
     * @return array<string, mixed>
     */
    public function respond(array $data): array
    {
        $sub = $data['options'][0] ?? null;
        $options = collect($sub['options'] ?? [])->pluck('value', 'name');

        if (($data['name'] ?? null) !== 'genetic' || $sub === null) {
            return $this->reply(__('discord.reply.unknown_command'), true);
        }

        return match ($sub['name'] ?? null) {
            'overview' => $this->reply($this->overview()),
            'progress' => $this->progressReply($options->get('druh')),
            'leaderboard' => $this->reply($this->leaderboard()),
            'milestone' => $this->milestoneReply($options->get('druh'), $options->get('stat')),
            default => $this->reply(__('discord.reply.unknown_command'), true),
        };
    }

    /** /genetic overview: X / 54, Indo Potential and a text progress bar. */
    public function overview(): string
    {
        $s = $this->progress->summary();
        $filled = (int) round($s['percent'] / 5);
        $bar = str_repeat('█', $filled).str_repeat('░', 20 - $filled);

        return "```\n"
            .sprintf("GENETIC MASTERY   %d / %d\n", $s['verified'], $s['total'])
            .$bar.' '.(int) round($s['percent'])." %\n"
            .sprintf("INDO POTENTIAL    %d / %d\n", $s['indo'], $s['indo_max'])
            ."```\n"
            .__('discord.reply.overview_also');
    }

    /** @return array<string, mixed> */
    private function progressReply(?string $species): array
    {
        $grid = $this->progress->grid();

        if ($species === null) {
            $s = $this->progress->summary();
            $lines = [__('discord.reply.progress_title', ['verified' => $s['verified'], 'total' => $s['total']])];
            foreach ($grid as $row) {
                $lines[] = __('discord.reply.progress_row', ['species' => $row['name'], 'count' => $row['verified'], 'total' => $row['total']]);
            }

            return $this->reply(implode("\n", $lines));
        }

        $row = $grid->get(strtoupper($species));

        if ($row === null) {
            return $this->reply(__('discord.reply.unknown_species'), true);
        }

        $lines = [__('discord.reply.species_title', ['species' => $row['name'], 'count' => $row['verified'], 'total' => $row['total']])];
        foreach ($row['milestones']->filter->isActive() as $milestone) {
            $lines[] = $this->statLine($milestone);
        }

        return $this->reply(implode("\n", $lines));
    }

    private function statLine(Milestone $milestone): string
    {
        return match ($milestone->state()) {
            MilestoneState::Verified => __('discord.reply.stat_verified', ['stat' => $milestone->statName(), 'name' => $this->plainName($milestone)]),
            MilestoneState::Pending => __('discord.reply.stat_pending', ['stat' => $milestone->statName()]),
            MilestoneState::Locked => __('discord.reply.stat_locked', ['stat' => $milestone->statName()]),
        };
    }

    private function leaderboard(): string
    {
        $rows = $this->progress->leaderboard(10);

        if ($rows->isEmpty()) {
            return __('discord.reply.leaderboard_empty');
        }

        $messages = app(DiscordMessages::class);
        $lines = [__('discord.reply.leaderboard_title')];
        foreach ($rows as $row) {
            $lines[] = __('discord.reply.leaderboard_row', [
                'rank' => $row['rank'],
                'name' => $messages->plain($row['user']->display_name),
                'count' => trans_choice('app.count.milestones', $row['discoveries']),
            ]);
        }

        return implode("\n", $lines);
    }

    /** /genetic milestone <druh> <stat>: only the caller sees it. @return array<string, mixed> */
    private function milestoneReply(?string $species, ?string $stat): array
    {
        $milestone = $this->progress->milestones()
            ->first(fn (Milestone $m) => $m->dinosaur === strtoupper((string) $species) && $m->stat === strtoupper((string) $stat));

        if ($milestone === null) {
            return $this->reply(__('discord.reply.unknown_milestone'), true);
        }

        if (! $milestone->isActive()) {
            return $this->reply(__('discord.reply.milestone_disabled', ['milestone' => $milestone->title()]), true);
        }

        $params = ['milestone' => $milestone->title(), 'code' => $milestone->code];
        $line = match ($milestone->state()) {
            MilestoneState::Verified => __('discord.reply.milestone_verified', $params + ['name' => $this->plainName($milestone)]),
            MilestoneState::Pending => __('discord.reply.milestone_pending', $params),
            MilestoneState::Locked => __('discord.reply.milestone_locked', $params),
        };

        return $this->reply($line."\n".__('discord.reply.milestone_link', ['url' => route('milestones.show', $milestone)]), true);
    }

    private function plainName(Milestone $milestone): string
    {
        return app(DiscordMessages::class)->plain($milestone->verifiedSubmission->user->display_name);
    }

    /** @return array<string, mixed> */
    private function reply(string $content, bool $ephemeral = false): array
    {
        $data = ['content' => $content, 'allowed_mentions' => ['parse' => []]];

        if ($ephemeral) {
            $data['flags'] = self::EPHEMERAL;
        }

        return ['type' => 4, 'data' => $data];
    }
}
