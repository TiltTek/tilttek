<!-- phil-dev source-sha256=d8538bd07ddb93c5b5fe1fe609f496f04889a320b25552a9882b44e400e480bf -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite couvre les périodes et leurs codes dans différents fuseaux horaires et à l'heure avancée, les saisons, les
classements et leurs refus, les mesures, les stockages en mémoire par `ScoreStoreTestCase`, `SnapshotStoreTestCase`,
`AppearanceStoreTestCase` et `LeagueStoreTestCase` (les contrats que tout stockage respecte), la projection avec le
journal de `tilttek/activity` : les scores par période, les contextes, les saisons, une entrée remise deux fois, les
annulations et les reconstructions, `Leaderboards` : ce que voit chaque spectateur, les égalités, les cercles, les
valeurs rapportées, les instantanés, l'exportation et l'effacement, et les ligues sur plusieurs semaines : les divisions
remplies dans l'ordre d'arrivée, la promotion et la relégation par palier, les membres absents, les semaines réglées en
retard, une division telle que ses membres la voient, les divisions formées par volume ; les critères de départage, les
fenêtres glissantes et les jours, les classements par objet, les titres et les équipes avec leur chance.

`ValkeyScoreStoreTest` exécute le contrat des scores contre un vrai Valkey dont `PHIL_TEST_VALKEY_URL` donne l'URL ; il
est ignoré sans elle, et il vide la base de données qu'on lui donne :

```sh
docker run --rm -d -p 127.0.0.1:16379:6379 valkey/valkey:8-alpine
PHIL_TEST_VALKEY_URL=redis://127.0.0.1:16379/15 vendor/bin/phpunit --no-configuration tests
```

`tests/Benchmark/RankingBench.php` mesure les lectures que fait une page (les dix premières lignes, le rang d'un membre,
combien sont classés, une division de trente) sur des classements de 1 000 et de 10 000 membres, en mémoire et, avec
`PHIL_TEST_VALKEY_URL`, dans Valkey. Dans le monodépôt, Infection tient la bibliothèque à 100 % de mutants couverts.

## Tester du code qui lit les classements

Construisez les stockages en mémoire, un `Journal` sur un `InMemoryActivityLog` avec des `LiveProjections` autour d'une
`LeaderboardProjection`, et une horloge que vous contrôlez ; enregistrez des activités, puis lisez :

```php
$journal->record(Activity::of('game:1:member:1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07 20:00 UTC')));

self::assertSame(1, $leaderboards->rank('games-month', $member)?->rank);
```

Un observateur qui note les instantanés et les règlements dont il est informé vérifie les récompenses. Pour une ligue,
avancez l'horloge au-delà de la fin d'une semaine, dans le fuseau horaire du classement, et appelez `settleDue()` ;
enregistrez les activités de la semaine suivante, puis lisez le palier de chaque membre.
