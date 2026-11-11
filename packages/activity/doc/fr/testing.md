<!-- phil-dev source-sha256=3034d7277daae38ad132c5474b645ecb2639a7d2ac817b327b67ea7fe5251ad7 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite couvre chaque valeur et ses refus, le journal en mémoire par `ActivityLogTestCase` — le contrat que tout
journal respecte —, le `Journal` et ses observateurs, et le projecteur : lots, points de reprise, échecs,
reconstructions et projections en direct. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants
couverts.

## Tester du code qui enregistre de l'activité

Construisez le `Journal` sur un `InMemoryActivityLog` avec une horloge que vous contrôlez, enregistrez par le code à
tester, puis relisez le journal :

```php
$log = new InMemoryActivityLog();
$journal = new Journal($log, new MockClock('2026-11-07 12:00:00'));

$listener = new CommentPostedListener($journal);
$listener($event);
$listener($event); // a listener run twice

self::assertSame(1, $journal->totals(Filter::all()->verbs('news.commented'))->count);
```

Une projection se teste de la même façon : ajoutez des entrées, lancez `Projector::catchUp()` avec
`InMemoryCheckpoints`, et regardez son état. `rebuild()` vérifie qu'elle remet à zéro ce qu'elle doit.
