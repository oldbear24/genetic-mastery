<?php

return [
    'announce' => [
        'content' => 'Gratulace, :mention! Nový ověřený milník.',
        'title' => 'NOVÝ GENETICKÝ MILNÍK',
        'species' => 'Druh',
        'stat' => 'Stat',
        'value' => 'Hodnota',
        'discoverer' => 'Objevitel',
        'progress' => 'Progres',
        'indo' => 'Indo Potential',
        'link' => 'Zobrazit milník a důkazy',
    ],

    'revoked' => [
        'edit' => '**OVĚŘENÍ ZRUŠENO** ~~NOVÝ GENETICKÝ MILNÍK · :milestone~~',
        'correction' => '**Oprava:** Ověření milníku :milestone bylo zrušeno. Progres je teď :verified / :total, Indo Potential :indo / :indo_max.',
    ],

    'commands' => [
        'genetic' => 'Genetic Mastery: progres clusteru a milníky',
        'overview' => 'Progres clusteru a Indo Potential',
        'progress' => 'Přehled po druzích, nebo staty jednoho druhu',
        'progress_species' => 'Druh, jehož staty chcete vidět',
        'leaderboard' => 'Deset nejlepších objevitelů',
        'milestone' => 'Stav jednoho milníku',
        'milestone_species' => 'Druh',
        'milestone_stat' => 'Stat',
    ],

    'reply' => [
        'overview_also' => 'Další: /genetic progress [druh] · /genetic leaderboard · /genetic milestone',
        'progress_title' => '**Progres po druzích** (:verified / :total)',
        'progress_row' => ':species :count / :total',
        'species_title' => '**:species** · :count / :total ověřeno',
        'stat_verified' => ':stat: OVĚŘENO – objevitel :name',
        'stat_pending' => ':stat: čeká na schválení',
        'stat_locked' => ':stat: zamčeno',
        'leaderboard_title' => '**Nejlepší objevitelé**',
        'leaderboard_row' => '#:rank :name · :count',
        'leaderboard_empty' => 'Zatím nikdo nemá ověřený objev.',
        'milestone_verified' => '**:milestone** (:code) · OVĚŘENO – objevitel :name',
        'milestone_pending' => '**:milestone** (:code) · čeká na schválení',
        'milestone_locked' => '**:milestone** (:code) · zamčeno',
        'milestone_link' => 'Detail: :url',
        'unknown_species' => 'Takový druh neznám.',
        'unknown_milestone' => 'Takový milník neexistuje.',
        'unknown_command' => 'Neznámý příkaz.',
    ],
];
