<x-layout :title="__('milestones.leaderboard.title')" current="leaderboard" class="flex flex-col gap-6">
    <div class="flex flex-col gap-1">
        <h1 class="m-0 font-display text-[30px] font-semibold">{{ __('milestones.leaderboard.title') }}</h1>
        <p class="m-0 text-ink-2">{{ __('milestones.leaderboard.subtitle') }}</p>
    </div>

    <div class="card overflow-x-auto px-2 py-2 sm:px-6">
        @if ($rows->isEmpty())
            <p class="m-0 p-4 text-ink-3">{{ __('milestones.leaderboard.empty') }}</p>
        @else
            <table class="w-full border-separate border-spacing-0 text-left">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.08em] text-ink-label uppercase">
                        <th scope="col" class="w-16 py-3 pr-3 font-semibold">{{ __('milestones.leaderboard.rank') }}</th>
                        <th scope="col" class="py-3 pr-3 font-semibold">{{ __('milestones.leaderboard.player') }}</th>
                        <th scope="col" class="py-3 pr-3 text-right font-semibold">{{ __('milestones.leaderboard.discoveries') }}</th>
                        <th scope="col" class="hidden py-3 text-right font-semibold sm:table-cell">{{ __('milestones.leaderboard.last_discovery') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr @if (auth()->user()?->is($row['user'])) class="bg-ok/5" @endif>
                            <td class="border-t border-line-soft py-3 pr-3 font-mono text-sm text-ink-3">#{{ $row['rank'] }}</td>
                            <td class="border-t border-line-soft py-3 pr-3">
                                <span class="flex items-center gap-3">
                                    <x-avatar :user="$row['user']" />
                                    <span class="font-medium">{{ $row['user']->display_name }}</span>
                                </span>
                            </td>
                            <td class="border-t border-line-soft py-3 pr-3 text-right font-mono text-sm">{{ $row['discoveries'] }} <span class="text-ink-3">{{ trans_choice('app.count.milestones_word', $row['discoveries']) }}</span></td>
                            <td class="hidden border-t border-line-soft py-3 text-right text-sm text-ink-3 sm:table-cell"><x-time :at="\Illuminate\Support\Carbon::parse($row['last_discovery_at'], 'UTC')" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layout>
