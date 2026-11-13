<!-- phil-dev source-sha256=732efa65defb061faaad3db66c0839f7d832c18d031c3a9ed9876917bb075a6d -->
# Célébrations

Une ligne pleine, un carton plein : un moment à célébrer. La bibliothèque dit quand ; la page le joue, avec le composant
de célébration du kit PHIL UX ou tout autre.

## Les événements

| Événement | Quand | Porte |
|---|---|---|
| `Event\BingoWon` | Un carton gagne, une fois par carton | `game`, `serial`, `player` — nul pour un carton imprimé —, `pattern` |
| `Event\SquareTicked` | Un geste coche une case d'un carton | `game`, `serial`, `square` |
| `Event\SquareCalled` | L'animateur appelle une case, la première fois | `game`, `square` |

Chaque événement ne porte que des identifiants : un écouteur qui pousse de nouveau le carton, remet un prix ou envoie un
message de Messenger lit le reste dans le dépôt. Les [mises à jour en direct](live-updates.md) les poussent vers les écrans des joueurs.

## Le gabarit du carton

`templates/card.html.twig` montre un carton à partir d'un `View\CardView`, construit par
`CardView::of($bingo, $game, $card)` : un tableau de ses rangées, chaque cellule avec son libellé — ou la case libre du
centre — et, pour les lecteurs d'écran, si elle est cochée. Enregistrez le dossier sous un espace de noms de
l'application :

```yaml
# config/packages/twig.yaml
twig:
    paths:
        '%kernel.project_dir%/vendor/tilttek/bingo/templates': TilttekBingo
```

La variable `labels` donne les mots de la page dans sa langue : `caption`, `ticked` et `free`, en anglais par défaut. Le
bloc `won`, vide par défaut, n'est dessiné que pour un carton qui a gagné. Avec la célébration du kit :

```twig
{% embed '@TilttekBingo/card.html.twig' with {card: view, labels: {caption: 'bingo.card'|trans, ticked: 'bingo.ticked'|trans, free: 'bingo.free'|trans}} %}
    {% block won %}<twig:Celebration message="{{ 'bingo.won'|trans }}" effect="fireworks" :autoplay="true" />{% endblock %}
{% endembed %}
```

## Le contrôleur Stimulus

Le gabarit porte le contrôleur `tilttek--bingo--card`, tiré du `assets/package.json` du paquet. Une fois le carton
gagnant, il envoie l'événement du DOM `bingo:won` sur la fenêtre, son détail contenant `game`, `serial` et `pattern`,
une fois par carton dans un onglet du navigateur ; là où le navigateur refuse le stockage, il l'envoie chaque fois. La
recette du kit joue sur lui :

```twig
<twig:Celebration message="{{ 'bingo.won'|trans }}" data-action="bingo:won@window->celebration#play" />
```

La recette est celle du kit, employée telle quelle : la bibliothèque n'ajoute aucun composant à elle.

## Accessibilité

Chaque cellule dit son libellé et, dans un texte visuellement masqué, qu'elle est cochée ; la légende nomme le numéro de
série du carton. La célébration du kit dit son message dans une région dynamique polie, ne dessine rien là où le
mouvement est réduit, ne joue aucun son et dure quelques secondes au plus.
