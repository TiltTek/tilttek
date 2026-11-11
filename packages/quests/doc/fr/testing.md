<!-- phil-dev source-sha256=0242a0e3abae2306c6e01505b77b9a0ed637d0af8d374881fdb42e1e1442e3b7 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite couvre les valeurs et leurs refus ; le lecteur et chaque définition erronée ; le catalogue et ses
enchaînements ; la progression des étapes ordonnées et libres ; les quatre magasins en mémoire par
`ContributionStoreTestCase`, `EnrolmentStoreTestCase`, `CompletionStoreTestCase` et `DuelStoreTestCase`, les contrats
que tout magasin respecte ; la projection avec le journal de `tilttek/activity` (quêtes personnelles, datées,
acceptées, abandonnées, enchaînées, de communauté, d'équipe et de duel, annulations et reconstructions) ; et `Quests` et
`Duels`. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

## Tester du code qui lit les quêtes

Construisez les magasins en mémoire, un `Journal` sur un `InMemoryActivityLog` avec `LiveProjections` autour d'une
`QuestProjection`, et une horloge que vous contrôlez ; enregistrez des activités, puis lisez :

```php
$journal->record(Activity::of('game:1:member:1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07 20:00')));

self::assertSame(QuestState::Completed, $quests->status($member, 'first-game')->state);
```

Un observateur qui note ce qu'on lui annonce vérifie les quêtes accomplies, leurs récompenses et les duels gagnés.
