<!-- phil-dev source-sha256=cbf9dccf511e80270786fee614b912c18e5a60b9e19ed681e1238cd9934f72d5 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `mbstring`.
- `phil/resource` 0.1, dont `ResourceReference` nomme une table, un détenteur et un acteur.
- `psr/clock` et `symfony/clock`, pour l'heure de chaque entrée ; `psr/event-dispatcher`, pour les événements.

## Installer

```sh
composer require tilttek/luck-tokens
```

## Câbler la bibliothèque

`LuckTokens` reçoit les règles et le registre, et facultativement un répartiteur d'événements, l'accès des acteurs aux
détenteurs, une horloge et la taille d'un registre :

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Store\SelfAccess;

$tokens = new LuckTokens(
    $rules,               // Contract\TokenRules: the kinds of tokens and the game masters of each table
    $ledger,              // Contract\TokenLedger: the append-only ledger of each table
    $dispatcher,          // Psr\EventDispatcher\EventDispatcherInterface, or null to dispatch nothing
    new SelfAccess(),     // Contract\HolderAccess: who spends the tokens of whom; each player their own by default
    new NativeClock(),    // Psr\Clock\ClockInterface: the time of the entries, written in UTC
    100_000,              // the most entries a table's ledger takes
);
```

`LuckTokens` ne garde rien entre deux appels : une seule instance sert donc toutes les requêtes d'un *worker*. Les
stockages en mémoire, `InMemoryTokenRules` et `InMemoryTokenLedger`, gardent tout dans le processus, pour les tests et
les scripts ; une application garde les règles et le registre dans son propre stockage, comme le décrit
[Points d'extension](extension-points.md).
