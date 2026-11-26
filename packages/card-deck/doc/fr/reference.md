<!-- phil-dev source-sha256=05f55371a54889b13e7634cad8302b7ff47b5d9893415d08cde60caf936da487 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Card` | Une carte : son identifiant, de 1 à 16 faces et un dos |
| `CardId`, `PlayerId`, `GameId` | Des identifiants opaques d'une carte, d'un joueur et d'une partie |
| `Face` | Ce que montre un côté d'une carte, sous forme de clé |
| `Deck` | De 1 à 1 024 cartes dans leur ordre déclaré, écrit en tableau et relu |
| `StandardDeck` | Les 52 cartes du jeu aux enseignes françaises et ses jokers |
| `ZoneId`, `ZoneKind` | Une zone — pioche, défausse, table, main, pile nommée — et son genre |
| `Gesture\Gesture` | Un geste du journal, écrit en tableau |
| `Gesture\Shuffle`, `Draw`, `Deal`, `Discard`, `Flip`, `TurnFace`, `Move`, `Collect` | Les gestes |
| `Gesture\Pick`, `Gesture\End` | D'où une carte est pigée, et où elle arrive |
| `Gesture\GestureCodec` | Relit les gestes écrits en tableaux |
| `DeckState` | L'état complet d'une partie : démarrer, appliquer, rejouer, empreinte, et les zones telles qu'un joueur les voit |
| `Shuffler` | Le mélange de Fisher-Yates sur un générateur aléatoire |
| `View\PlayerView`, `View\ZoneView`, `View\SeenCard` | Ce qu'un joueur peut voir d'une partie, zone par zone, carte par carte |
| `CardTable` | Ouvre, démarre, joue, montre et termine les parties d'un magasin |
| `GameRecord` | Ce qu'un magasin garde d'une partie, avec sa version |
| `Reveal` | Une partie terminée, publiée pour que chacun la vérifie |
| `Contract\GameStore` | Le port du magasin |
| `Store\InMemoryGameStore` | Le magasin en mémoire, pour les tests |
| `Exception\CardDeckException` et ses quatre exceptions | [Exceptions](exceptions.md) |
