<?php

declare(strict_types=1);

/*
 * The events of the roleplaying calendar, one row per entry, read by Tilttek\RpgCalendarEvents\EntryReader. Each rule
 * is written in the grammar of phil/almanac and was checked against its source on the day `verified` names; the names
 * are the package's own. A themed period of daily prompts carries `prompts`: its prompts stay with the application.
 * Conventions, and any event whose date its organiser sets each year, are added by the application, not here.
 * data/VERSION is the day these rows last changed. doc/data.md lists them and explains how to add one.
 */
return [
    [
        'id' => 'gm-day',
        'names' => ['en' => 'GM’s Day', 'fr' => 'Journée des MJ'],
        'rule' => '03-04',
        'source' => 'https://sjgames.com/ill/archive/February_29_2012',
        'community' => 'tabletop-roleplaying',
        'verified' => '2026-11-21',
        'tags' => ['game-masters', 'appreciation'],
    ],
    [
        'id' => 'rpgaday',
        'names' => ['en' => 'RPGaDAY', 'fr' => 'RPGaDAY'],
        'rule' => '08-01',
        'days' => 31,
        'prompts' => true,
        'source' => 'https://www.autocratik.com/',
        'community' => 'tabletop-roleplaying',
        'verified' => '2026-11-21',
        'tags' => ['daily-prompts', 'writing'],
    ],
    [
        'id' => 'nagademon',
        'names' => ['en' => 'National Game Design Month (NaGaDeMon)', 'fr' => 'Mois de la création de jeux (NaGaDeMon)'],
        'rule' => '11-01',
        'days' => 30,
        'source' => 'https://itch.io/jam/national-game-design-month-2025',
        'community' => 'game-designers',
        'verified' => '2026-11-21',
        'tags' => ['game-design', 'challenge'],
    ],
];
