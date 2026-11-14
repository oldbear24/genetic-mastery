<?php

use Database\Seeders\MilestoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests run against a fresh database (SQLite in memory locally,
| PostgreSQL in the second CI job) with the milestone catalogue seeded.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(MilestoneSeeder::class);
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');
