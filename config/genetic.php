<?php

/*
|--------------------------------------------------------------------------
| Genetic Mastery rules
|--------------------------------------------------------------------------
|
| Species, stats and the required value are the source for the milestone
| seed. Codes are stable identifiers (they end up in milestone codes such
| as GIGA_MELEE_250), names are shown in the UI exactly as the game shows
| them, so they are not translated.
|
*/

return [

    // Shown under the logo as "<name> · ARK cluster"; without it just "ARK cluster".
    'cluster_name' => env('CLUSTER_NAME'),

    'species' => [
        'REX' => 'Rex',
        'RAPTOR' => 'Raptor',
        'CARNO' => 'Carnotaurus',
        'GIGA' => 'Giganotosaurus',
        'THERI' => 'Therizinosaurus',
        'SPINO' => 'Spinosaurus',
        'ALLO' => 'Allosaurus',
        'YUTY' => 'Yutyrannus',
        'MEGALO' => 'Megalosaurus',
    ],

    'stats' => [
        'HEALTH' => 'Health',
        'STAMINA' => 'Stamina',
        'OXYGEN' => 'Oxygen',
        'FOOD' => 'Food',
        'WEIGHT' => 'Weight',
        'MELEE' => 'Melee Damage',
    ],

    'required_value' => (int) env('MILESTONE_REQUIRED_VALUE', 250),

    'indo' => [
        'base' => (int) env('INDO_BASE', 150),
        'per_milestone' => (int) env('INDO_PER_MILESTONE', 25),
    ],

    /*
    | Admins are members with one of the Discord roles below, or users whose
    | Discord ID is listed explicitly (a fallback when roles break).
    */
    'admin_role_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_ROLE_IDS', ''))))),
    'admin_user_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_USER_IDS', ''))))),
    'allow_self_approve' => (bool) env('ALLOW_SELF_APPROVE', false),

    // Membership and roles are re-checked at most this many minutes after the last check (AUTH-4).
    'roles_refresh_minutes' => (int) env('ROLES_REFRESH_MINUTES', 10),

    'upload' => [
        'max_mb' => (int) env('UPLOAD_MAX_MB', 15),
        'max_files' => (int) env('UPLOAD_MAX_FILES', 10),
        'max_total_mb' => (int) env('UPLOAD_MAX_TOTAL_MB', 60),
        'max_megapixels' => (int) env('UPLOAD_MAX_MEGAPIXELS', 40),
        'disk' => env('UPLOAD_DISK', 'local'),
        'thumbnail_width' => 640,
    ],

    'player_note_max' => 1000,

    // Daily database snapshot and evidence mirror. Point BACKUP_DISK at an
    // off-site disk (S3 compatible storage or a mounted volume) in production.
    'backup' => [
        'disk' => env('BACKUP_DISK', 'local'),
        'path' => env('BACKUP_PATH', 'backups'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    ],

    'rate_limits' => [
        'submissions_per_hour' => 10,
        'submissions_per_day' => 30,
    ],

];
