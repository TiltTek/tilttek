<!-- phil-dev source-sha256=18d2a88aaecc043593eaac39ca4f488f8085c747210b295d0f8fd0e17d496ba8 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : chaque borne d'un autocollant, d'une
transformation et de limites, les références d'image qui seraient des scripts ou des données en ligne refusées, les
textes nettoyés et vérifiés, les détenteurs et les surfaces référencés par une adresse courriel refusés, les requêtes
lues depuis du JSON et tout le reste refusé, les placements accordés, placés, refusés par chaque règle et chaque
limite, déplacés, supprimés, masqués par un modérateur ou à la main et rétablis, les spectateurs qui désactivent les
autocollants, les autocollants sortis du catalogue, la reprise et l'effacement, les événements, les stockages et les
planches d'autocollants complétées par `tilttek/sets`. Dans le monodépôt, Infection maintient la bibliothèque à 100 %
des mutants couverts.

`tests/Fuzz/placement.php` est la cible de fuzzing d'une requête de placement : tout texte se lit comme une requête
dans les bornes absolues, que les limites d'un site acceptent ou refusent avec `StickerRefused`, ou est refusé avec
`InvalidSticker`, jamais avec une autre exception. `composer fuzz` la lance.

`tests/Benchmark/StickersBench.php` mesure la surface la plus remplie qu'un site permet — 100 placements — montrée à un
spectateur, et une requête de placement lue depuis du JSON.

## Tester une application

Donnez à `Stickers` les adaptateurs en mémoire, une politique de surfaces avec les surfaces assignées, une horloge à
vous et un répartiteur qui garde les événements :

```php
$surfaces = new InMemorySurfacePolicy();
$surfaces->assign($profile, $member);
$stickers = new Stickers($catalog, new InMemoryOwnershipStore(), new InMemoryPlacementStore(), $surfaces, new Limits(), $frozenClock, $recordingDispatcher);
```
