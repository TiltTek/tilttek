<!-- phil-dev source-sha256=0e4ff9b053cfafe3318d8e14d5b825b8a74a64d8622c624981c629c56d20177e -->
# Gestes

Chaque changement d'une partie est un geste : un objet-valeur sous `Tilttek\CardDeck\Gesture`, appliqué par
`DeckState::apply()`, enregistré dans le journal de la partie par `CardTable::play()`. `apply()` retourne un nouvel état
et laisse tel quel celui qu'on lui a donné ; un geste qu'il refuse lève `IllegalGesture` et ne change rien — une
distribution qui manque de cartes n'en distribue aucune.

| Geste | Ce qu'il fait | Refusé quand |
|---|---|---|
| `Shuffle(?ZoneId $zone)` | Mélange une zone sur place, la pioche par défaut, face cachée, à partir du nombre suivant de la graine | La zone n'est pas ouverte |
| `Draw(PlayerId $player, Pick $from, ?ZoneId $zone, bool $faceUp)` | Une carte du dessus, du dessous ou au hasard d'une zone, la pioche par défaut, à la fin de la main du joueur | La zone n'est pas ouverte, ou est vide |
| `Deal(list<PlayerId> $players, int $count, bool $faceUp, ?ZoneId $from)` | Des cartes du dessus d'une zone, une à la fois autour des joueurs dans l'ordre donné, jusqu'à ce que chacun en détienne `count` de plus | La zone contient moins de `count` × joueurs cartes |
| `Discard(CardId $card)` | La carte sur le dessus de la défausse, face visible | Le paquet ne contient pas cette carte |
| `Flip(CardId $card)` | La carte retournée là où elle se trouve | La carte se trouve dans la pioche |
| `TurnFace(CardId $card, int $face)` | Une autre face de la carte montrée, numérotée à partir de 0 ; face visible ou cachée comme elle l'était | La carte n'a pas cette face |
| `Move(CardId $card, ZoneId $to, End $at, bool $faceUp)` | La carte vers le dessus ou le dessous d'une zone | Le paquet ne contient pas cette carte, ou la partie contient 256 zones |
| `Collect(ZoneId $from, ZoneId $to, End $at, bool $faceUp)` | Chaque carte d'une zone, dans leur ordre, vers le dessus ou le dessous d'une autre | La première zone n'est pas ouverte |

Une carte qui arrive dans la pioche est toujours face cachée, quoi que le geste demande. Une distribution va à 1 à 64
joueurs, chacun une seule fois, et donne de 1 à 1 024 cartes à chacun ; un geste hors de ces bornes est refusé à sa
création, avec `InvalidDefinition`.

La bibliothèque vérifie ce que l'état de la partie permet ; à qui le tour, et quel joueur peut déplacer quelle carte,
sont des règles du jeu que l'application vérifie avant de jouer le geste.

## Mélanger n'est pas démarrer une partie

Une partie démarre une seule fois, par `DeckState::start()` — ou `CardTable::start()` : chaque carte face cachée dans la
pioche, dans l'ordre du paquet, puis mélangée à partir du nombre 0 de la graine. Un `Shuffle` pendant la partie mélange
une zone et laisse les autres : pour remélanger la défausse dans la pioche, ramassez-la, puis mélangez.

```php
$state = $state
    ->apply(new Collect(ZoneId::discard(), ZoneId::drawPile(), End::Bottom))
    ->apply(new Shuffle());
```

Une nouvelle partie est une nouvelle `GameRecord`, avec une nouvelle graine et un nouvel engagement.

## Écrire et lire des gestes

`Gesture::toArray()` écrit un geste en données simples, comme un magasin le garde ; `GestureCodec::fromArray()` le
relit, en vérifiant chaque champ et en refusant tout autre contenu avec `InvalidDefinition` :

```php
['gesture' => 'draw', 'player' => 'u-101', 'from' => 'random', 'zone' => 'draw', 'face_up' => false]
```

## Rejouer un journal

`DeckState::replay($deck, $seed, $clientSeed, $gestures)` démarre la partie et applique le journal geste par geste : un
même paquet, une même graine, une même graine du client et un même journal donnent toujours le même état.
`DeckState::fingerprint()` est le SHA-256 des places, des côtés et des faces de chaque carte et du nombre suivant de la
graine : deux états de même empreinte sont la même partie. Elle ne contient ni la graine ni la graine du client.
