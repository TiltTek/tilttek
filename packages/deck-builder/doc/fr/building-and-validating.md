<!-- phil-dev source-sha256=88fadd8242d12048e2cde0fef66cff17210d0e61be90861714df57a55c82a5f7 -->
# Construire et valider des paquets

## Un paquet

Un `Deck` contient son identifiant, son nom, le code du format pour lequel il est construit, et ses cartes : jusqu'à
1 000 cartes distinctes, chacune avec 1 à 999 exemplaires, dans l'ordre où elles ont été ajoutées. Un paquet ne change
jamais ; chaque changement donne un nouveau paquet :

| Méthode | Donne |
|---|---|
| `withCard($card, $quantity = 1)` | Plus d'exemplaires d'une carte, ajoutée à la fin quand le paquet ne la contenait pas |
| `withoutCard($card, $quantity = 1)` | Moins d'exemplaires ; s'il n'en reste aucun, la carte est retirée |
| `withQuantity($card, $quantity)` | Ce nombre d'exemplaires, à la place de la carte ; 0 la retire |
| `renamed($name)` | Un autre nom |
| `limitedTo($owned)` | Pas plus d'exemplaires de chaque carte que n'en possède la collection |
| `copiedAs($id, $name, $original)` | Une copie liée à son original ; `Decks::copy()` l'appelle |

`size()` compte chaque exemplaire ; `quantityOf($card)` donne celui d'une carte. Un paquet contient n'importe quel
identifiant de carte : que la carte existe et soit permise est la question du validateur, de sorte qu'un paquet en
construction n'est jamais refusé pour une carte retirée depuis.

`Deck::fromArray()` et `fromJson()` lisent ce qu'écrivent `toArray()` et `toJson()` — une colonne de base de données, un
formulaire, une exportation —, chaque défaut étant une `InvalidDeck`.

## Le validateur

`DeckValidator::validate($deck, $on, $owned = null)` vérifie un paquet selon les règles de son format ce jour-là :

1. la taille du paquet, selon le plus petit et le plus grand paquet ;
2. chaque carte, dans l'ordre du paquet : inconnue du catalogue de cartes, bannie, restreinte en deçà de sa quantité, ou
   au-delà de ses exemplaires ;
3. chaque carte que le catalogue connaît, selon les exemplaires que la collection possède, quand une collection est
   donnée.

Chaque violation est retournée, aucune n'arrête les autres. Un format que le catalogue de formats ne contient pas lève
`UnknownFormat` : le paquet ne peut pas être vérifié du tout.

`Validation` donne `isValid()`, `messages()` — les phrases dans leur ordre — et `ofType()`, avec le paquet, la `Ruling`
appliquée et le jour. Chaque `Violation` porte son `ViolationType`, son message, la carte concernée et les valeurs dont
le message a été construit, pour une application qui écrit ses propres messages ou les traduit.

| `ViolationType` | Message, par exemple | Valeurs |
|---|---|---|
| `TooFewCards` | `The deck has 38 cards; the format "league" needs at least 40.` | `size`, `limit` |
| `TooManyCards` | `The deck has 61 cards; the format "league" allows at most 60.` | `size`, `limit` |
| `Banned` | `"Old Relic" (old-relic) is banned by the format "league".` | `card`, `quantity`, `by` |
| `Restricted` | `The deck has 2 copies of "Storm Call" (storm-call); the format "league" restricts it to 1.` | `card`, `quantity`, `limit`, `by` |
| `TooManyCopies` | `The deck has 4 copies of "Ember Fox" (ember-fox); at most 3 are allowed.` | `card`, `quantity`, `limit` |
| `UnknownCard` | `The card "ghost" is not in the card catalog.` | `card`, `quantity` |
| `NotOwned` | `The deck has 3 copies of "Ember Fox" (ember-fox); the collection owns 2.` | `card`, `quantity`, `owned` |

## Des paquets limités à une collection

Une collection appartient à l'application : l'étagère d'un membre, les boîtes d'un club, ce qu'un membre a apporté à la
table. Elle atteint la bibliothèque par `Contract\OwnedCards`, qui dit les exemplaires possédés d'une carte ;
`Catalog\OwnedCardList` contient une collection lue une fois depuis un tableau. Le validateur signale chaque carte
au-delà des exemplaires possédés ; `Deck::limitedTo()` donne le paquet réduit à ceux-ci, pour jouer avec ce qu'on a sous
la main.
