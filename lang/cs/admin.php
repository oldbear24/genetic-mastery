<?php

return [
    'queue' => [
        'title' => 'Žádosti ke schválení',
        'subtitle' => 'Nejstarší nahoře · filtr podle druhu, statu nebo hráče',
        'filter_label' => 'Filtrovat žádosti',
        'filter_placeholder' => 'Filtr: druh, stat, hráč',
        'item_meta' => ':player · :when · :images',
        'parallel' => '[2,4] :count žádosti na tento milník|[5,*] :count žádostí na tento milník',
        'empty' => 'Nic nečeká na schválení.',
        'empty_filtered' => 'Filtru neodpovídá žádná čekající žádost.',
    ],

    'detail' => [
        'required' => 'požadovaná hodnota :value',
        'claim_line' => 'Žádost :id · odesláno',
        'original' => 'Originál',
        'discord_id' => 'Discord ID :id',
        'player_history' => ':verified ověřeno · :rejected zamítnuto',
        'player_note' => 'Poznámka hráče',
        'no_note' => 'Bez poznámky.',
        'evidence' => 'Důkazy',
        'evidence_hint' => 'Kliknutím obrázek zvětšíte · originály se stahují jako soubory',
        'checks' => 'Kontroly',
        'nothing_selected' => 'Vyberte žádost ze seznamu.',
        'not_pending' => 'Tato žádost už nečeká na schválení: :status.',
        'own_claim' => 'Tohle je vaše vlastní žádost. Rozhodnout o ní musí jiný admin.',
        'public_notes' => 'Veřejné poznámky',
        'internal_notes' => 'Interní poznámky',
    ],

    'checks' => [
        'parallel_tag' => 'SOUBĚŽNÉ',
        'parallel' => 'Další čekající žádost na tento milník: :id od hráče :name, :order (:when).',
        'order_earlier' => 'odeslaná dřív',
        'order_later' => 'odeslaná později',
        'later_tag' => 'POZDĚJŠÍ ŽÁDOST',
        'later' => 'Existuje dřívější žádost: :id od hráče :name (:when). Schválení této žádosti vyžaduje potvrzení.',
        'duplicate_tag' => 'DUPLICITA',
        'duplicate' => 'Soubor :name má stejný SHA-256 jako obrázek v žádosti :other (:player).',
        'ok_tag' => 'OK',
        'ok' => 'V jiných žádostech se žádný stejný obrázek nenašel.',
    ],

    'decision' => [
        'approve' => 'Schválit',
        'public_note' => 'Veřejná poznámka (nepovinná, ukáže se v historii)',
        'public_note_placeholder' => 'např. Ověřeno podle záznamu mutací',
        'effect_single' => 'Ověří milník a pošle oznámení na Discord.',
        'effect_parallel' => '{1} Ověří milník, uzavře druhou žádost jako „Uzavřeno, schválena jiná žádost“ a pošle oznámení na Discord.|[2,*] Ověří milník, uzavře ostatní žádosti (:count) jako „Uzavřeno, schválena jiná žádost“ a pošle oznámení na Discord.',
        'confirm_later' => 'Potvrzuji, že schvaluji pozdější žádost, i když na schválení čeká dřívější.',
        'approve_button' => 'Schválit žádost',
        'approve_anyway' => 'Schválit i tak',
        'reject' => 'Zamítnout',
        'reason' => 'Důvod (povinný, hráč ho uvidí)',
        'reason_placeholder' => 'Co chybí nebo nesedí?',
        'reject_button' => 'Zamítnout žádost',
    ],

    'result' => [
        'approved' => 'Schváleno · :code je ověřený. Progres clusteru je teď :verified / :total a Indo Potential :indo / :indo_max. Objevitel: :name. Oznámení na Discord čeká ve frontě.',
        'superseded' => '{1} Jedna další žádost byla uzavřena.|[2,4] :count další žádosti byly uzavřeny.|[5,*] :count dalších žádostí bylo uzavřeno.',
        'rejected' => 'Zamítnuto · :id. Hráč :name uvidí důvod ve svém přehledu a může poslat novou žádost.',
    ],

    'errors' => [
        'not_pending' => 'Žádost :id už nečeká na schválení (:status).',
        'already_verified' => 'Tento milník už mezitím ověřil jiný admin. Žádost nejde schválit.',
        'confirm_later' => 'Na tento milník čeká dřívější žádost. Schválení pozdější žádosti je potřeba potvrdit.',
        'own_claim' => 'O vlastní žádosti nemůžete rozhodnout.',
        'reason_required' => 'Napište důvod zamítnutí. Hráč ho uvidí.',
    ],
];
