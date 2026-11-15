<!-- phil-dev source-sha256=92afdfc28c7207b84442db5a3b8a5a0a77ab2fc0c794a8604ce2e4ff46269fa5 -->
# Gabarits

## Fonctions Twig

| Fonction | Retourne |
|---|---|
| `tilttek_leaderboards_standing(board, viewer = null, period = null, limit = 10, among = [], object = null)` | Le `Standing` d'un classement dans une période (celle qui est en cours par défaut) tel que le spectateur le voit, parmi les membres du cercle quand `among` les énumère, pour l'`object` dans un classement par objet |
| `tilttek_leaderboards_frozen(board, period, viewer = null, limit = 10)` | L'instantané d'une période tel que le spectateur le voit, ou null |
| `tilttek_leaderboards_division(board, viewer, period = null)` | La `Division` du spectateur dans un classement doté d'une ligue, dans une semaine (celle qui est en cours par défaut), ou null quand il n'y prend pas part |
| `tilttek_leaderboards_league(board, viewer)` | Une `LeagueView` : tout ce que montre l'écran de ligue du membre |
| `tilttek_leaderboards_teams(board, teams, period = null, object = null)` | Les équipes classées, les membres de chacune par son nom (`{red: ['member:1'], blue: ['member:2']}`), sous forme de `TeamRow` |
| `tilttek_leaderboards_titles(member)` | Les titres que le membre détient maintenant |
| `tilttek_leaderboards_holds(board, member, object = null)` | Si le membre détient maintenant le titre du classement, pour l'objet dans un classement par objet |
| `tilttek_leaderboards_appearance(member)` | Le choix du membre, pour le formulaire qui le modifie |
| `tilttek_leaderboards_celebration(board, viewer, period = null)` | Une `CelebrationView` quand le spectateur a atteint le sommet à la clôture de la période (la dernière révolue par défaut) : promu dans le palier le plus haut, dans un classement doté d'une ligue, ou premier de son instantané ; null sinon, dans un classement sans période révolue (`ever`, une fenêtre), ou quand `celebration` est désactivé |
| `tilttek_leaderboards_kit(recipe)` | Le nom sous lequel une recette du kit est installée : `ui:Badge` avec le sous-répertoire `ui` du Toolkit |
| `tilttek_leaderboards_domain()` | Le domaine de traduction du gabarit |

Les membres s'écrivent `kind:identifier`, comme `member:44`.

## Le gabarit de classement

```twig
{{ include('@TilttekLeaderboards/standing.html.twig', {
    standing: tilttek_leaderboards_standing('games-month', 'member:44'),
    names: {'member:3': 'Alice'},
}) }}
```

Il produit le rendu du nom du classement (la clé de traduction `boards.<name>`), de la période, du nombre de membres
classés, de « Classement final » pour un instantané, des lignes dans une liste ordonnée, et de la ligne du spectateur
sous celles-ci quand elle n'en fait pas partie. Une ligne affiche « Vous » pour le spectateur, le nom que donne `names`
pour un membre qui a choisi de paraître sous son nom, le pseudonyme pour celui qui en a choisi un, et « Un membre »
pour tous les autres, y compris un membre nommé absent de `names`. L'application ne passe que les noms des membres
qu'elle affiche.

Le gabarit n'est fourni avec aucun CSS : ses classes commencent par `tilttek-leaderboards`, et la ligne du spectateur
porte `tilttek-leaderboards__row--self`.

## Le gabarit de division

```twig
{% set division = tilttek_leaderboards_division('weekly-xp', 'member:44') %}
{% if division %}
    {{ include('@TilttekLeaderboards/division.html.twig', {division: division, names: {'member:3': 'Alice'}}) }}
{% endif %}
```

Il produit le rendu du nom du classement, du palier (la clé de traduction `tiers.<name>`) et du numéro de la division,
de la semaine, du nombre de membres qui montent et qui descendent, et des lignes de la division. Chacune se lit comme
dans un classement et est marquée selon l'endroit où elle irait si la semaine finissait maintenant : sa classe
`tilttek-leaderboards__row--promoted`, `--stayed` ou `--relegated`, son `data-movement` et le texte `movement.*`
traduit. Seuls les membres de la division sont affichés, chacun selon son choix d'apparence.

## L'écran de ligue

```twig
{{ include('@TilttekLeaderboards/league.html.twig', {
    league: tilttek_leaderboards_league('weekly-xp', 'member:44'),
    names: {'member:3': 'Alice'},
    appearance_url: path('profile_leaderboards'),
}) }}
```

L'écran propre au membre dans un classement hebdomadaire doté d'une ligue : le nom du classement, son palier et la fin
de la semaine, l'échelle des paliers à partir du plus haut avec le sien marqué `aria-current="step"`, où il est allé la
semaine dernière, sa division (le gabarit de division ci-dessus) ou, avant qu'il marque des points cette semaine, ce qui
le fait entrer dans une, et comment les membres de sa division le voient, avec un lien vers le formulaire qui le
modifie quand `appearance_url` est donné. Rien de cela n'est public.

`LeagueView` contient le classement, ses paliers à partir du plus bas (`ladder()` à partir du plus haut), le palier du
membre, sa `Division` ou null, la fin de la semaine, sa `Membership` de la semaine dernière ou null, et son
`Appearance`.

## Le classement d'équipes

```twig
{{ include('@TilttekLeaderboards/teams.html.twig', {
    board: 'house-cup',
    rows: tilttek_leaderboards_teams('house-cup', {red: ['member:1', 'member:2'], blue: ['member:3']}),
}) }}
```

Un tableau des équipes : le rang, le nom (la clé de traduction `team_names.<name>`), combien de membres ont marqué des
points, leurs scores additionnés, le jet de chance quand le classement l'active, et le total.

## La célébration

```twig
{{ include('@TilttekLeaderboards/celebration.html.twig', {
    celebration: tilttek_leaderboards_celebration('weekly-xp', 'member:44'),
}) }}
```

Sur l'écran qu'un membre ouvre après avoir atteint le sommet (la page du classement, celle vers laquelle mène une
notification de `TopReached`), il joue la recette `Celebration` du kit PHIL UX : « Vous avez fini premier de … ! »,
« Vous avez pris la tête de … ! » ou « Vous avez atteint …, le palier le plus haut de … ! », et l'effet configuré. Avec
`celebrate_on: period_close`, une première place est célébrée pendant la période qui suit l'instantané ; avec
`celebrate_on: score_change`, une première place est célébrée tant que le membre la tient, dans la période en cours,
une fois qu'il l'a prise parmi au moins deux membres. Une promotion dans le palier le plus haut est célébrée pendant la
semaine qui suit dans les deux cas. La célébration est celle du membre : elle dit « Vous » et ne montre rien de lui à
personne d'autre. Elle ne produit aucun rendu quand `celebration` est null. Avec `autoplay: false`, il attend un événement DOM `celebrate` ou un bouton portant
`data-action="celebration#play"`, comme le décrit la recette. Un gabarit de l'application le dessine où elle veut avec le
seul bloc :

```twig
{% use '@TilttekLeaderboards/celebration.html.twig' %}
{% set celebration = tilttek_leaderboards_celebration('games-month', app.user.reference) %}
{{ block('tilttek_leaderboards_celebration') }}
```

La recette le rend accessible : le message est dit dans une région vivante polie (`role="status"`, `aria-live="polite"`)
et reste affiché ; les confettis ou les feux d'artifice ne sont dessinés que là où le mouvement n'est pas réduit
(`prefers-reduced-motion`, ou `data-motion="reduce"` sur un ancêtre) ; leur canevas est caché aux lecteurs d'écran et
laisse passer chaque clic ; il n'y a aucun son. La célébration joue chaque fois que l'écran est affiché tant qu'elle tient :
une application qui la veut une seule fois garde ce que le membre a vu (le classement, la période et le sommet) et
passe ensuite `celebration: null`. `CelebrationView` contient le `Board`, le code de la période, le `Summit`, l'effet et, pour une
ligue, le palier atteint. Sa racine porte `data-board`, `data-period` et `data-summit`.

## Les recettes du kit

L'écran de ligue et le classement d'équipes sont construits des recettes `Card`, `Badge` et `Empty`, et la célébration de
la recette `Celebration`, d'un kit de Symfony UX Toolkit (le kit PHIL UX, mis en forme par phil-ux, ou le kit Shadcn, dont les recettes portent les mêmes noms et les
mêmes propriétés), que l'application installe, puis dont elle produit le rendu par `symfony/ux-twig-component` :

```sh
bin/console ux:install card badge empty celebration --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Les gabarits nomment les recettes d'après le sous-répertoire de la configuration `ux_toolkit` de l'application
(`tilttek_leaderboards_kit('Card')` vaut `ui:Card` avec `ui`), ou d'après `kit_subdirectory` quand il est défini. La
recette `Celebration` est celle du kit PHIL UX lui-même, à partir de sa version 0.5.0-alpha ; avec un autre kit,
remplacez `celebration.html.twig` ou désactivez `celebration`. Les gabarits de classement, d'instantané et de division n'exigent aucune recette.

## Traductions

`translations/tilttek_leaderboards.en.yaml` et `.fr.yaml` contiennent les textes : `you`, `a_member`, `period`,
`participants`, `frozen`, `empty`, `division`, `promotion`, `relegation`, `movement.promoted`, `.stayed` et
`.relegated`, les `league.*` de l'écran de ligue, les `teams.*` du classement et les `celebration.first_place`,
`celebration.lead` et `celebration.highest_tier` de la célébration. Les noms des classements appartiennent
à l'application, sous `boards.<name>`, tout comme ceux des paliers, sous `tiers.<name>`, et ceux des équipes, sous
`team_names.<name>`.
