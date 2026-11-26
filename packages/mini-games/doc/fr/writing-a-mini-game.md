<!-- phil-dev source-sha256=0cf5dc0ea4f02cce6785cb6c768f92413aeb29cabd4b6bf8341b540e23f0bcac -->
# Écrire un mini-jeu

Un mini-jeu implémente `Contract\MiniGame`, qui étend le `Contract\Game` de `tilttek/game-engine` : tout ce que le guide
du moteur pour écrire un jeu dit d'un jeu s'applique — sa définition, sa mise en place, ses coups, son issue, sa vue,
chaque méthode pure. Un mini-jeu ajoute trois méthodes.

| Méthode | Ce qu'elle donne |
|---|---|
| `scoring()` | Un `Scoring` : dans quel sens vont ses scores, et la statistique de siège où son issue rapporte le score |
| `controls()` | Ses actions et les touches qui font chacune, au moins une |
| `share()` | Le `ShareGrid` d'une table : comment chaque étape s'est passée, jamais ce qu'elle était |

## Une table d'un seul siège

Un mini-jeu se joue seul : sa définition assoit un joueur, `minimumSeats: 1` et `maximumSeats: 1`. Les jeux quotidiens
ouvrent une table par joueur et par jour, avec l'identifiant du joueur comme siège ([Jeux quotidiens](daily-games.md)).
Un jeu dont la phase n'a pas de tours — `TurnOrder::Free`, la valeur par défaut — laisse son unique siège jouer à tout
moment.

## Pointage

`ScoreOrder::HigherIsBetter` convient à un jeu de points ou de bonnes réponses ; `ScoreOrder::LowerIsBetter`, à un jeu
d'essais, de coups ou de secondes. Le jeu déclare la statistique dans sa définition et la rapporte dans son issue :

```php
statistics: [Statistic::seat('score')],
// …
return Outcome::won($state->seats, 'solved', new Statistics([], [$state->seats[0] => ['score' => 4]]));
```

Une fin qui n'a pas de score équitable — un code jamais trouvé — omet la statistique : elle est lue comme nulle,
n'établit aucun record et ne compte dans aucun classement. `ScoreOrder` compare strictement : deux scores égaux ne
battent ni l'un ni l'autre, et `isNewRecord()` accepte le premier score et refuse un score égal
([Fins, records et historique](endings-and-history.md)).

## Commandes

Un `Control` nomme une action — `higher`, `guess`, des mots en minuscules joints par `-` — et les 1 à 12 touches qui la
font, comme valeurs de `KeyboardEvent.key` : `ArrowUp`, `Enter`, `7`. L'application les lie, par un contrôleur
Stimulus, au coup que l'action représente, et les affiche près des boutons ; chaque action du jeu a des touches, de
sorte que le jeu se joue au clavier seul.

## Sans animation

La vue d'un mini-jeu est une simple donnée : ce que le joueur voit maintenant, jamais une étape d'animation. Une
application peut animer un changement, mais rien dans le jeu n'attend une animation, et un joueur qui préfère le
mouvement réduit ne perd rien : la vue après un coup dit tout ce que l'animation dirait.

## Partage

`share()` transforme une table en rangées de `Cell` — `Hit`, `Near`, `Miss`, `Empty` — et en un sommaire comme `4/10`.
Il dit comment chaque étape s'est passée, jamais ce qu'elle était : ni un chiffre, ni un nombre, ni une direction qui
laisserait un autre joueur deviner la réponse. [Partager un résultat](sharing.md) montre comment la grille s'écrit.

## Les deux jeux d'exemple

`Game\BullsAndCows` et `Game\HigherOrLower` sont écrits comme modèles : lisez-les avant d'écrire un jeu à vous
([Les jeux d'exemple](example-games.md)). Chacun relit ses données par une seule méthode privée qui vérifie chaque
champ, de sorte qu'un cache qui rend autre chose est refusé par `InvalidGame` plutôt que mal lu.
