<!-- phil-dev source-sha256=d8bbf5ea1dc70b3490f636b0a74692dd5265975d4d2057ff198f859df575ed90 -->
# Écrire un jeu de taverne

Un jeu de taverne implémente `Contract\TavernGame`, qui étend le `Contract\Game` de `tilttek/game-engine` : tout ce que
le guide du moteur pour écrire un jeu dit s'applique — une définition, une mise en place, des coups, une issue, une vue,
chaque méthode pure. Un jeu de taverne ajoute `toAct()` et accepte le délai d'un tour.

## Tours

La phase du jeu n'a pas de tours du moteur — `TurnOrder::Free`, la valeur par défaut —, et ses données contiennent
`Turns` :

```php
public function setup(GameState $state, Randomness $random): array
{
    return ['turns' => Turns::start($state->seats)->toData(), /* … */];
}

public function allows(GameState $state, Move $move): bool
{
    $turns = Turns::fromData($state->data['turns'] ?? null);

    return match ($move->type) {
        'my-game.play' => $turns->isTurnOf($move->seat),
        self::TIMEOUT => null === $move->seat && $turns->isTurnOf($move->text('seat')),
        default => false,
    };
}
```

`play()` rend `Turns::next()` dans les données quand le tour se termine. `toAct()` rend le siège qui joue, ou nul une
fois la partie terminée.

## Le délai

`TavernGame::TIMEOUT`, `tavern.timeout` avec `{seat}`, est fait par la table elle-même — sans siège — pour le siège qui
joue. Le jeu termine le tour de ce siège selon ses règles, et n'enlève rien de plus que le tour : aucun point perdu
au-delà du tour, aucun siège retiré de la table.

## Dés

`DiceCup::roll($count, $random)` lance de 1 à 10 dés à six faces par `tilttek/dice`, avec le `Randomness` du coup, de
sorte que rejouer le journal lance les mêmes dés et que le moteur enregistre que le coup a tiré au hasard.

## Cartes

Un jeu de cartes garde ses cartes avec `tilttek/card-deck`, sans le modifier. Crazy Eights montre la voie : sa mise en
place tire une graine de 32 octets du hasard de la table, et ses données contiennent cette graine et les gestes des
cartes, écrits par leur `toArray()` ; chaque coup les rejoue dans un `DeckState`, ajoute ses propres gestes, et sa vue
construit la main de chaque siège à partir de `DeckState::seenBy()`, qui ne montre jamais la main d'un autre. Comme la
graine du jeu de cartes vient de celle de la table, l'engagement envers la graine de la table couvre aussi les
mélanges.

## Égalités

Une issue peut nommer plusieurs gagnants — des cargaisons égales, des mains égales —, et sa raison le dit : `tie`,
`blocked`. Une estimation des chances compte les égalités à part ([Chances](odds.md)).

## Les règles

Les jeux de la bibliothèque sont des jeux traditionnels, libres de droits, et leurs règles sont rédigées pour elle. Le
jeu d'un éditeur n'est publié qu'avec le consentement de l'éditeur au dossier.
