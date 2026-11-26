<!-- phil-dev source-sha256=dbf2e4635aa0877627737f5a4d433fa759f90229e6542f90811e6adaf6be3658 -->
# Cartes et zones

## Cartes et faces

Une `Card` a une identité, un `CardId`, et ce qu'elle montre : une `Face` ou plusieurs — jusqu'à 16, pour une carte qui
se retourne ou se transforme — et un dos, la `Face` que chacun voit quand elle est face cachée. L'identité ne dit
jamais ce que la carte montre : deux jokers ont deux identifiants et une seule face, et une carte cachée n'est jamais
montrée par son identifiant.

```php
$card = Card::of('card-17', 'QH');                                     // one face, the back "back"
$moon = new Card(new CardId('m-1'), [new Face('new'), new Face('full')], new Face('night-sky'));
```

Les identifiants, les clés de face, les identifiants de joueur et les noms de pile suivent un seul motif : de 1 à 64
lettres ASCII, chiffres, points, soulignés et traits d'union, en commençant par une lettre ou un chiffre. Ils sont
opaques : la bibliothèque les compare et ne les lit jamais. Une clé de face est ce que l'application dessine — une
image, un texte — et un identifiant de joueur renvoie aux comptes de l'application : ni l'un ni l'autre ne contient
jamais le nom ou l'adresse d'une personne.

## Paquets

Un `Deck` contient de 1 à 1 024 cartes, chaque identifiant une seule fois, dans l'ordre où elles sont déclarées :
l'ordre de la pioche avant le mélange qui démarre une partie. `toArray()` et `Deck::fromArray()` l'écrivent en données
simples et le relisent.

`StandardDeck::create()` donne les 52 cartes du jeu aux enseignes françaises — faces `AS` à `KC`, valeur `A`, `2` à
`10`, `J`, `Q`, `K` puis enseigne `S`, `H`, `D`, `C` — et autant de jokers, `JK`, qu'on en demande, avec des
identifiants `card-01` et suivants qui ne disent rien de la face. Tout autre paquet est une liste de cartes que
l'application déclare : les cartes d'un jeu à elle, ou d'un jeu du domaine public.

## Zones

À chaque instant, chaque carte se trouve dans exactement une zone. Un `ZoneId` s'écrit en texte :

| Zone | Écriture | Vue |
|---|---|---|
| La pioche | `draw` | Toujours face cachée ; son nombre de cartes seulement, jamais son ordre |
| La défausse | `discard` | Chaque carte face visible ou cachée |
| La table | `table` | Chaque carte face visible ou cachée |
| La main d'un joueur | `hand:<player>` | Chaque carte par son joueur ; les cartes face visible par tous |
| Une pile nommée | `pile:<name>` | Chaque carte face visible ou cachée, pour les jeux à zones libres — un marché, une rivière, une réserve |

La pioche, la défausse et la table sont ouvertes au début d'une partie ; une main ou une pile nommée s'ouvre la
première fois qu'une carte y arrive, et reste, vide ou non. Une partie ouvre au plus 256 zones. Dans une zone, les
cartes sont en ordre, le dessus en premier ; une carte pigée dans une main va à sa fin.

## Une zone vide

Une zone est vide quand elle ne contient aucune carte : `DeckState::isEmpty()` et `count()` le disent, quelles que
soient les cartes. Une pioche qui ne contient que des jokers n'est pas vide — y piger donne un joker — et piger dans
une pioche vide est refusé. La bibliothèque ne lit jamais une face pour décider de ce qu'une zone contient.
