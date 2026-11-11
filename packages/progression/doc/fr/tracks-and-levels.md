<!-- phil-dev source-sha256=82a464955862318cb911d5869c4896202d5049fdce236424d4a5a61fcf1d4d82 -->
# Pistes et niveaux

## Courbes

Une `Curve` dit à partir de quelle expérience commence chaque niveau. Le niveau 1 commence à 0 ; chaque niveau suivant
commence plus haut que le précédent, au plus à 10^15, et une courbe a de 2 à 1000 niveaux.

| Courbe | Les niveaux 2, 3, 4… commencent à | Exemple |
|---|---|---|
| `Curve::linear($step, $levels)` | `step`, `2 × step`, `3 × step`… | `Curve::linear(100, 50)` |
| `Curve::quadratic($base, $levels)` | `base`, `4 × base`, `9 × base`… | `Curve::quadratic(50, 60)` |
| `Curve::exponential($base, $factor, $levels)` | chaque palier vaut `factor` fois le précédent, le premier `base`, arrondi | `Curve::exponential(100, 1.5, 40)` : 100, 250, 475… |
| `Curve::table($thresholds)` | les seuils qu'écrit l'application | `Curve::table([300, 900, 2700])` |

Le nombre de niveaux est de 100 par défaut. `$curve->level($experience)` retourne un `Level` : son `number`,
l'`experience`, le `floor` où il a commencé, le seuil suivant `next` (null au dernier niveau), et `gained()`, `span()`,
`missing()` et `percent()` pour une barre de progression. Une table est la façon d'écrire les seuils d'expérience
propres à un jeu.

## Règles

Une `ExperienceRule` donne de l'expérience pour un geste : les verbes qu'elle lit, sous forme de motifs comme
`game.played` ou `game.*` ; l'expérience par unité de quantité de l'activité, ou par activité avec
`perQuantity: false` ; un `Cap` par sujet sur une `Period` (un jour, une semaine ISO à partir du lundi, un mois, une
année, ou pour toujours) ; et les faits que doit contenir le contexte de l'activité.

```php
new ExperienceRule('runs', ['game.ran'], 50, new Cap(200, Period::Day), ['role' => 'gm']);
```

Les périodes d'un plafond sont lues dans le fuseau horaire de `Tracks`. Un plafond qui laisse moins que ce que donne la
règle rapporte ce qui reste ; un plafond atteint ne rapporte rien, et le geste compte quand même pour les séries.

## Pistes

Un `Track` contient ses règles, sa courbe, à qui va son expérience, ses saisons et ses jalons :

- `Subject::Member`, par défaut, donne l'expérience au membre qui a fait le geste ;
- `Subject::Object` la donne à l'objet du geste : un personnage, dont le meneur de jeu attribue l'expérience par une
  activité `character.awarded` dont la quantité est le montant. Une activité sans objet n'apporte rien à une telle
  piste.

Plusieurs pistes peuvent lire les mêmes verbes : une activité apporte alors de l'expérience à chacune. L'expérience
n'est jamais dépensée ni écrite à la main ; elle vient de l'activité, et une annulation la reprend.

## À partir d'une configuration

```php
$tracks = Tracks::fromArray([
    'player' => [
        'curve' => ['type' => 'quadratic', 'base' => 50, 'levels' => 60],
        'subject' => 'member',
        'seasons' => ['length' => 'quarter'],
        'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 20, 'cap' => ['amount' => 100, 'period' => 'day']]],
        'milestones' => ['veteran' => ['level' => 10], 'thousand' => ['experience' => 1000]],
    ],
    'character' => ['curve' => ['type' => 'table', 'thresholds' => [300, 900, 2700]], 'subject' => 'object', 'rules' => [
        'awarded' => ['verbs' => ['character.awarded'], 'experience' => 1],
    ]],
], 'America/Toronto');
```

Un type de courbe, un sujet, une période, une longueur de saison ou une date qui n'existe pas, ou une courbe à qui il
manque son palier, sa base, son facteur ou ses seuils, lève `InvalidProgression`.

## Positions

`$progression->standing($subject, 'player')` retourne un `Standing` : la piste ; la saison en cours (aucune sur une
piste sans saisons ou entre deux saisons nommées) ; le `Level` de l'expérience qui compte, celle de la saison, ou toute
l'expérience sur une piste sans saisons ; et l'expérience `lifetime` de toutes les saisons ensemble.
`standings($subject)` en retourne un par piste, par nom.
