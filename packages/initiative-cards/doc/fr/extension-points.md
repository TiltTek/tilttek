<!-- phil-dev source-sha256=d5f5bce9d9282bf4bf839ec16e85d35ed9b0d8096fc4214ad0331a02960f01d8 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les rencontres | Implémentez `Contract\EncounterStore`, et passez-le à `InitiativeTracker`. |
| Ordonner les cartes d'un autre jeu | Implémentez `Contract\CardRanking`, et passez-le à `InitiativeTracker`. |
| Agir sur ce qui s'est passé | Implémentez `Contract\InitiativeObserver`, et passez-le à `InitiativeTracker`. |
| Donner leur hasard aux mélanges | Passez un `Random\Randomizer`. |

## Le stockage

`Contract\EncounterStore` garde les rencontres, chacune à une version :

- `encounter()` en donne une par son identifiant, ou null ;
- `save()` en garde une à la version suivante et la retourne. Une à la version 0 est nouvelle, et le stockage n'a aucune
  rencontre de son identifiant ; toute autre n'est gardée que si le stockage la détient à cette version, et
  `EncounterConflict` est levée sinon, rien n'étant gardé.

Deux joueurs qui terminent des tours en même temps lisent tous deux la version 4 : la première sauvegarde garde la
version 5, et la seconde est refusée avec `EncounterConflict` ; l'application relit la rencontre, et le tour que le
second joueur voulait terminer n'est plus le tour courant.

`Store\InMemoryEncounterStore` les garde en mémoire, tant que l'objet dure : il sert aux tests. Une application les
garde dans sa base de données. Une rencontre contient des chaînes, des entiers, des booléens, les listes d'identifiants
de cartes de ses piles, ses combattants — chacun des chaînes, un entier et des booléens — et son jeu, que
`Deck::toArray()` écrit et que `Deck::fromArray()` relit.

## Le classement

`Contract\CardRanking` ordonne les faces d'un jeu :

- `ranks()` dit s'il connaît une face : une rencontre ne commence que sur un jeu dont il connaît chaque face ;
- `compare()` est négatif quand la première face agit avant la seconde, positif quand elle agit après, zéro à égalité ;
- `reshuffles()` dit si distribuer la face remet la défausse dans la pioche avant la ronde suivante.

Le gestionnaire de rencontres lit la première face de chaque carte. Un jeu de cartes numérotées, la plus haute d'abord :

```php
use Override;
use Tilttek\CardDeck\Face;
use Tilttek\InitiativeCards\Contract\CardRanking;

final readonly class NumberedRanking implements CardRanking
{
    #[Override]
    public function ranks(Face $face): bool
    {
        return 1 === preg_match('/^n([1-9][0-9]?)$/D', $face->key);
    }

    #[Override]
    public function compare(Face $one, Face $other): int
    {
        return (int) substr($other->key, 1) <=> (int) substr($one->key, 1);
    }

    #[Override]
    public function reshuffles(Face $face): bool
    {
        return 'n1' === $face->key;
    }
}
```

## L'observateur et ses événements

`Contract\InitiativeObserver::notify()` est informé de chaque fait une fois la rencontre gardée, par identifiants
seulement :

| Événement | Quand | Porte |
|---|---|---|
| `Event\EncounterStarted` | Une rencontre a commencé. | |
| `Event\RoundDealt` | Une ronde a été distribuée. | la ronde |
| `Event\DeckReshuffled` | La défausse a été remise dans la pioche. | |
| `Event\TurnStarted` | Le tour d'un combattant a commencé. | le combattant, s'il est caché |
| `Event\TurnEnded` | Le tour d'un combattant a pris fin. | le combattant |
| `Event\RoundEnded` | Tous les combattants debout ont agi. | la ronde |
| `Event\CombatantJoined` | Un combattant s'est joint. | le combattant, s'il est caché |
| `Event\CombatantLeft` | Un combattant est parti. | le combattant |
| `Event\CombatantDefeated` | Un combattant a été vaincu. | le combattant |
| `Event\CombatantRevived` | Un combattant vaincu est de nouveau debout. | le combattant |
| `Event\CombatantRevealed` | Un combattant caché a été révélé. | le combattant |
| `Event\CombatantHidden` | Un combattant a été caché. | le combattant |
| `Event\EncounterEnded` | La rencontre a été terminée. | |

Chaque événement implémente `Event\InitiativeEvent`, dont `encounter` est un identifiant ; les événements d'un
combattant implémentent `Event\CombatantEvent`, qui ajoute `combatant`. Un changement précède le tour qu'il transmet :
`TurnEnded`, puis `TurnStarted` ou `RoundEnded`. Une application envoie un message à partir d'eux — par Messenger, avec
les identifiants seuls — et lit le reste dans le stockage : une poussée de la vue de chaque membre après chaque
événement, un avis au joueur dont le tour commence, sauf si le combattant est caché.

## Le générateur aléatoire

Les mélanges de `start()`, `deal()` et `join()` utilisent le `Random\Randomizer` donné, par le mélange de Fisher-Yates
de `tilttek/card-deck`. Par défaut, c'est un générateur sur le moteur `Secure`. Un test en donne un sur un moteur à germe,
comme `Mt19937`, pour répéter ses mélanges.
