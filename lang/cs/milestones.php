<?php

return [
    'state' => [
        'locked' => 'Zamčeno',
        'pending' => 'Čeká na schválení',
        'verified' => 'Ověřeno',
    ],

    'cell' => [
        'verified' => 'OVĚŘENO',
        'pending' => 'ČEKÁ',
        'pending_sub' => 'na schválení',
        'locked' => 'Zamčeno',
        'aria_verified' => ':milestone: ověřeno, objevitel :name',
        'aria_pending' => ':milestone: čeká na schválení',
        'aria_locked' => ':milestone: zamčeno',
    ],

    'grid' => [
        'title' => 'Milníky',
        'subtitle' => ':species × :stats · každý potřebuje :points · milník se ověřuje jen jednou za celý cluster',
        'legend' => 'Legenda',
        'species' => 'Druh',
        'row_verified' => ':count / :total ověřeno',
        'caption' => 'Mřížka milníků: druhy v řádcích, staty ve sloupcích',
    ],

    'recent' => [
        'title' => 'Poslední objevy',
        'discoverer' => 'Objevitel: :name',
        'empty' => 'Zatím tu nejsou žádné objevy.',
    ],

    'leaders' => [
        'title' => 'Nejlepší objevitelé',
        'full' => 'Celý žebříček',
        'empty' => 'Žebříček je zatím prázdný.',
    ],

    'leaderboard' => [
        'title' => 'Žebříček',
        'subtitle' => 'Hráči podle počtu ověřených objevů. Při shodě rozhoduje dřívější datum odeslání posledního objevu.',
        'rank' => 'Pořadí',
        'player' => 'Hráč',
        'discoveries' => 'Objevy',
        'last_discovery' => 'Poslední objev',
        'empty' => 'Zatím nikdo nemá ověřený objev.',
    ],

    'detail' => [
        'required' => ':code · požadovaná hodnota :value',
        'verified_banner' => 'OVĚŘENO – objevitel :name',
        'verified_counts' => 'Započítáno clusteru: +1 do progresu, +:indo Indo Potential',
        'pending_banner' => 'Čeká na schválení',
        'pending_text' => 'K milníku je odeslaná žádost, která čeká na posouzení.',
        'locked_banner' => 'Zamčeno',
        'locked_text' => 'Tento milník zatím není splněný.',
        'discovered_by' => 'Objevitel',
        'submitted_as' => 'odesláno jako „:name“',
        'submitted' => 'Odesláno',
        'claim' => 'žádost :id',
        'verified_by' => 'Ověřující admin',
        'admin' => 'admin',
        'verified_at' => 'Ověřeno',
        'after_submission' => ':duration po odeslání',
        'evidence' => 'Důkazy',
        'admin_note' => 'Poznámka admina',
        'history' => 'Historie',
        'new_claim' => 'Nová žádost',
    ],

    'history' => [
        'verified' => 'Ověřeno · admin :admin',
        'announced' => 'oznámeno na Discordu',
        'submitted' => 'Odeslaná žádost :id · :name',
    ],

    'earlier' => [
        'title' => 'Dřívější pokusy',
        'admins_only' => 'JEN PRO ADMINY',
        'empty' => 'Žádné další pokusy.',
        'attempt' => ':id · :name',
        'reviewed_by' => 'admin :admin',
        'revoked_by' => 'ověření zrušeno · admin :admin',
    ],
];
