<!-- phil-dev source-sha256=385b71349efcbfaef272baa9cc9b6caef0103b73b0e50877d1ec5d7d171c59d6 -->
# Parties et magasins

## La table

`CardTable` fait jouer les parties d'un magasin et ne garde rien entre deux appels :

| Méthode | Ce qu'elle fait |
|---|---|
| `open(GameId, Deck, Seed): Commitment` | Enregistre une nouvelle partie et retourne l'engagement à publier |
| `start(GameId, string $clientSeed): DeckState` | Démarre la partie une fois : la pioche est mélangée |
| `play(GameId, int $version, Gesture): DeckState` | Applique un geste quand la partie est à cette version, et l'enregistre |
| `view(GameId, ?PlayerId): PlayerView` | Ce qu'un joueur — ou un spectateur — peut voir |
| `finish(GameId): Reveal` | Termine la partie et révèle ce qu'il faut à chacun pour la vérifier |

Une partie se joue une fois démarrée et jusqu'à ce qu'elle soit terminée ; `IllegalGesture` dit le contraire. Un
journal contient au plus 10 000 gestes, de sorte qu'une relecture reste bornée.

## Versions

Une `GameRecord` a une version, augmentée de un à chaque changement : 0 à l'ouverture, 1 au démarrage, puis une par
geste et une à la fin de la partie. Une vue porte la version dont elle est tirée ; le geste du joueur est joué contre
elle, et `ConcurrentChange` le refuse quand un autre geste a été enregistré avant — deux joueurs qui pigent la
dernière carte en même temps obtiennent une carte et un refus. L'application relit la partie et laisse le joueur
décider de nouveau.

## Le port du magasin

`Contract\GameStore` a deux méthodes : `find(GameId): ?GameRecord`, et `save(GameRecord)`, qui n'écrit l'enregistrement
que par-dessus la version qui le précède — aucune pour la version 0 — et lève `ConcurrentChange` autrement.

L'enregistrement contient le paquet, la graine, la graine du client, le journal, si la partie est terminée et sa
version. L'état n'est jamais enregistré : il est rejoué à partir du journal.

## Un magasin à vous

Un magasin sur une base de données garde une ligne par partie, avec `Deck::toArray()` et chaque `Gesture::toArray()` en
JSON, la graine sous la forme de `Seed::hex()`, et la version dans une colonne que la mise à jour vérifie :

```sql
UPDATE card_game SET record = :record, version = :version WHERE id = :id AND version = :version - 1
```

Aucune ligne mise à jour signifie `ConcurrentChange`. Il relit l'enregistrement avec `Deck::fromArray()` et
`GestureCodec::fromArray()`. Il chiffre la graine au repos, et ne la journalise jamais.

`Store\InMemoryGameStore` est le magasin pour les tests.
