<?php

return [
    'welcome' => 'Vítejte zpět, :name',
    'summary_pending' => '{1} Jedna vaše žádost čeká na schválení.|[2,4] :count vaše žádosti čekají na schválení.|[5,*] :count vašich žádostí čeká na schválení.',
    'summary_rejected' => '{1} Jedna zamítnutá žádost čeká na opravu.|[2,4] :count zamítnuté žádosti čekají na opravu.|[5,*] :count zamítnutých žádostí čeká na opravu.',
    'summary_none' => 'Nic nečeká na schválení. Vyberte milník a pošlete novou žádost.',
    'new_claim' => 'Nová žádost',

    'stats' => [
        'discoveries' => 'Vaše objevy',
        'of_verified' => 'z :count ověřených milníků',
        'leaderboard' => 'Žebříček',
        'tied' => 'shoda na :count · při shodě rozhoduje dřívější poslední objev',
        'leading' => 'bez shody s ostatními',
        'no_rank' => 'zatím bez objevu',
    ],

    'submissions' => [
        'title' => 'Vaše žádosti',
        'show_all' => 'Zobrazit všech :count',
        'empty' => 'Zatím tu nejsou žádné vaše žádosti.',
    ],

    'grid' => [
        'title' => 'Milníky',
        'subtitle' => 'Vaše objevy jsou zvýrazněné. O zamčené a čekající milníky můžete pořád zažádat.',
        'you' => 'Vy',
        'your_claim' => 'Vaše žádost',
        'in_review' => 'Čeká',
        'rejected' => 'Zamítnuto',
        'locked' => 'Zamčeno',
        'aria_mine_verified' => ':milestone: ověřeno, váš objev',
        'aria_verified' => ':milestone: ověřeno, objevitel :name',
        'aria_mine_pending' => ':milestone: vaše žádost čeká na schválení',
        'aria_pending' => ':milestone: čeká na schválení, můžete také zažádat',
        'aria_mine_rejected' => ':milestone: vaše žádost byla zamítnuta, můžete zažádat znovu',
        'aria_locked' => ':milestone: zamčeno, můžete zažádat',
    ],
];
