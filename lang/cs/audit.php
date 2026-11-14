<?php

return [
    'title' => 'Historie změn',
    'subtitle' => 'Každá admin akce se starou a novou hodnotou. Záznamy nejde upravit ani smazat.',
    'filter_action' => 'Akce',
    'filter_actor' => 'Admin',
    'filter_submission' => 'ID žádosti',
    'when' => 'Kdy',
    'actor' => 'Admin',
    'action_col' => 'Akce',
    'entity' => 'Žádost',
    'change' => 'Změna',
    'reason' => 'Důvod',
    'system' => 'systém',
    'empty' => 'Filtru neodpovídá žádný záznam.',

    'action' => [
        'submission_approved' => 'Schválení',
        'submission_rejected' => 'Zamítnutí',
        'submission_superseded' => 'Uzavřeno, schválena jiná žádost',
        'submission_revoked' => 'Zrušení ověření',
        'submission_reopened' => 'Znovuotevření',
        'submission_reopened_after_revoke' => 'Vráceno ke schválení po zrušení ověření',
        'submission_verifier_changed' => 'Změna ověřujícího admina',
        'note_added' => 'Poznámka',
    ],

    'field' => [
        'status' => 'stav',
        'reviewed_by' => 'ověřující admin',
        'revoked_by' => 'admin zrušení',
        'superseded_by' => 'kvůli žádosti',
        'rejection_reason' => 'důvod zamítnutí',
        'revoke_reason' => 'důvod zrušení',
        'visibility' => 'viditelnost',
        'note_id' => 'poznámka',
        'body' => 'text',
    ],

    'visibility' => [
        'public' => 'veřejná',
        'internal' => 'interní',
    ],
];
