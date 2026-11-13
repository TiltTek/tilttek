<!-- phil-dev source-sha256=d260bf0b615447aa453429d42da0f71f66af4ed90121f4212ea0feed76b8e874 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `tilttek/activity` 0.1 : les gestes qui cochent les cartons d'un congrès, et l'observateur de son journal.
- `psr/clock` : le moment de chaque victoire.
- `psr/event-dispatcher` : le répartiteur de `BingoWon`, `SquareTicked` et `SquareCalled`.
- Twig et Stimulus seulement pour le gabarit du carton et son contrôleur ; voir [Célébrations](celebrations.md).
- `symfony/mercure` seulement pour pousser les gestes d'un jeu ; voir [Mises à jour en direct](live-updates.md).

## Installer

```sh
composer require tilttek/bingo
```

## Brancher la bibliothèque

```php
use Tilttek\Bingo\{Bingo, CardDealer};
use Tilttek\Bingo\Activity\BingoActivityObserver;

$bingo = new Bingo(
    $store,                              // Contract\BingoStore: the games and their cards
    new CardDealer($secret),             // the server's secret, at least 32 bytes, kept out of the code
    $dispatcher,                         // a PSR-14 dispatcher
    $clock,                              // a PSR-20 clock
);

$observer = new BingoActivityObserver($bingo);   // among the observers of the activity Journal
```

Le secret tire chaque carton : gardez-le dans les secrets de l'application, jamais dans un dépôt ni dans le navigateur,
et gardez-le aussi longtemps que les cartons d'un jeu sont vérifiés — un autre secret tire d'autres cartons. `Bingo`,
`CardDealer` et l'observateur ne gardent rien entre deux appels : une seule instance sert toutes les requêtes d'un
*worker* ; `Store\InMemoryBingoStore` garde tout dans le processus, pour les tests et les scripts.
