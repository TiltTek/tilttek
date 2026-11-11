<!-- phil-dev source-sha256=f69f6c3fbba3eb03d3e8460a06fb9fc98929ec53308b29c4c4b15b1b44ffdf5d -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite couvre les courbes et leurs refus ; les règles, les plafonds et les périodes à travers les fuseaux horaires ;
les saisons ; les pistes à partir d'une configuration ; les séries (grâce, unités gelées, trous, gestes en avance sur le
moment présent) ; le gardien et son balayage dans les fuseaux horaires des membres ; les magasins en mémoire par
`ExperienceStoreTestCase`, `MilestoneStoreTestCase` et `StreakStoreTestCase`, les contrats que tout magasin respecte ;
la projection avec le journal de `tilttek/activity` (niveaux, jalons, plafonds, objets, saisons, annulations et
reconstructions) ; et `Progression`. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants
couverts.

## Tester du code qui lit la progression

Construisez les magasins en mémoire, un `Journal` sur un `InMemoryActivityLog` avec `LiveProjections` autour d'une
`ProgressionProjection`, et une horloge que vous contrôlez ; enregistrez des activités, puis lisez :

```php
$journal->record(Activity::of('game:1:member:1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07 20:00')));

self::assertSame(1, $progression->streak($member, 'daily-play')->current);
```

Un observateur qui note ce qu'on lui annonce vérifie les montées de niveau, les jalons et les séries brisées.
