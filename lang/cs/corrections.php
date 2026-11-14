<?php

return [
    'title' => 'Opravy',
    'revoke' => 'Zrušit ověření',
    'revoke_reason' => 'Důvod zrušení (povinný)',
    'revoke_placeholder' => 'Proč ověření neplatí?',
    'revoke_effect' => 'Milník se vrátí mezi neověřené (progres −1, Indo Potential −:indo). Žádosti uzavřené kvůli tomuto ověření se vrátí ke schválení a na Discord odejde oprava.',
    'reopen' => 'Znovu otevřít',
    'reopen_effect' => 'Žádost se vrátí ke schválení a pak se o ní rozhodne běžným způsobem.',
    'change_verifier' => 'Změnit ověřujícího admina',
    'verifier' => 'Ověřující admin',
    'save_verifier' => 'Uložit admina',
    'add_note' => 'Přidat poznámku',
    'note_body' => 'Text poznámky',
    'note_visibility' => 'Viditelnost',
    'note_public' => 'Veřejná (ukáže se v historii)',
    'note_internal' => 'Interní (jen pro adminy)',
    'save_note' => 'Uložit poznámku',
    'own_claim' => 'Vlastní žádost může opravit jen jiný admin.',
    'trail' => 'Historie žádosti',
    'trail_empty' => 'U této žádosti zatím není žádná admin akce.',

    'done' => [
        'revoked' => 'Ověření žádosti :id je zrušené. Progres clusteru je teď :verified / :total a Indo Potential :indo. Oprava na Discord čeká ve frontě.',
        'reopened_count' => '{1} Jedna uzavřená žádost se vrátila ke schválení.|[2,4] :count uzavřené žádosti se vrátily ke schválení.|[5,*] :count uzavřených žádostí se vrátilo ke schválení.',
        'reopened' => 'Žádost :id znovu čeká na schválení.',
        'verifier_changed' => 'Ověřující admin žádosti :id je teď :name.',
        'note_added' => 'Poznámka je uložená.',
    ],

    'errors' => [
        'not_verified' => 'Žádost :id není ověřená.',
        'not_reopenable' => 'Žádost :id nejde znovu otevřít (:status). Otevřít jde jen zamítnutou žádost nebo žádost se zrušeným ověřením.',
        'milestone_verified' => 'Milník už je ověřený jinou žádostí, takže tuto nejde znovu otevřít.',
        'player_has_pending' => 'Hráč už má u tohoto milníku jinou čekající žádost.',
        'not_admin' => 'Vybraný uživatel není admin.',
        'reason_required' => 'Napište důvod zrušení ověření.',
    ],
];
