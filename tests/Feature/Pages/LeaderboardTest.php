<?php

use App\Models\Milestone;
use App\Models\Submission;
use App\Services\ClusterProgress;

it('ranks players by verified discoveries, earlier last discovery wins a tie (LB-1)', function () {
    $kira = player('Kira');
    $eva = player('Eva');
    $joe = player('Joe');

    $milestones = Milestone::orderBy('sort_order')->get();

    // Kira: 2, last sent 3 days ago. Eva: 2, last sent yesterday. Joe: 1.
    Submission::factory()->for($kira)->verified()->create(['milestone_id' => $milestones[0]->id, 'submitted_at' => now()->subDays(5)]);
    Submission::factory()->for($kira)->verified()->create(['milestone_id' => $milestones[1]->id, 'submitted_at' => now()->subDays(3)]);
    Submission::factory()->for($eva)->verified()->create(['milestone_id' => $milestones[2]->id, 'submitted_at' => now()->subDays(6)]);
    Submission::factory()->for($eva)->verified()->create(['milestone_id' => $milestones[3]->id, 'submitted_at' => now()->subDay()]);
    Submission::factory()->for($joe)->verified()->create(['milestone_id' => $milestones[4]->id, 'submitted_at' => now()->subDays(10)]);
    // Pending and rejected claims do not count.
    Submission::factory()->for($joe)->create(['milestone_id' => $milestones[5]->id]);
    Submission::factory()->for($joe)->rejected()->create(['milestone_id' => $milestones[6]->id]);

    $board = app(ClusterProgress::class)->leaderboard();

    expect($board->map(fn ($row) => [$row['rank'], $row['user']->display_name, $row['discoveries']])->all())
        ->toBe([[1, 'Kira', 2], [2, 'Eva', 2], [3, 'Joe', 1]]);

    expect(app(ClusterProgress::class)->standingOf($eva))->toBe(['rank' => 2, 'discoveries' => 2, 'tied_with' => 1]);

    $this->get(route('leaderboard'))
        ->assertOk()
        ->assertSeeInOrder(['#1', 'Kira', '#2', 'Eva', '#3', 'Joe'])
        ->assertSeeInOrder(['Kira', '2', 'milníky', 'Eva', '2', 'milníky', 'Joe', '1', 'milník']);
});

it('shows the top discoverers on the home page with Czech plurals', function () {
    $kira = player('Kira');
    Milestone::orderBy('sort_order')->limit(5)->get()
        ->each(fn ($m) => Submission::factory()->for($kira)->verified()->create(['milestone_id' => $m->id]));

    $this->get('/')->assertSeeInOrder(['Nejlepší objevitelé', '#1', 'Kira', '5', 'milníků']);
});

it('shows an empty state without discoveries', function () {
    $this->get(route('leaderboard'))->assertOk()->assertSee('Zatím nikdo nemá ověřený objev.');
});
