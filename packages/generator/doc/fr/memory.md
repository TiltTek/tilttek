<!-- phil-dev source-sha256=2060c0b03dd6ba5c6281540277ea08f07ce068c77e9e5c4121db14ee47c252f5 -->
# Tables à mémoire

Une table à mémoire est un générateur dont le résultat dépend du précédent : la météo d'aujourd'hui suit celle d'hier,
un voyage compte ses jours et les milles qui restent, un groupe qui explore une carte d'hexagones sait où il se trouve.
Un tel générateur a un état (`state`) : des valeurs portées d'un résultat au suivant. Chaque tirage reçoit l'état que le
dernier tirage a laissé et retourne le résultat avec le nouvel état. La bibliothèque ne garde rien entre les tirages :
l'application tient l'état (par campagne, par table, par personne) et le repasse ; `tilttek/generator-bundle` le garde
pour une application qui le veut.

## Écrire un état

```yaml
generators:
  travel:
    state:
      day:
        start: "1"
        next: "{{ state.day + 1 }}"
      remaining:
        start: "60"
        next: "{% set left = state.remaining - 12 - roll('2d6') %}{{ left < 0 ? 0 : left }}"
      pace: brisk
    templates: "Day {{ state.day }}: {{ state.remaining }} miles to go at a {{ state.pace }} pace."
```

Chaque valeur de `state` a un nom (une lettre ou `_`, puis des lettres, des chiffres ou `_`) et soit un gabarit, donné
une fois puis gardé, soit une table d'un gabarit `start` et d'un gabarit `next` facultatif. Un état contient au plus 32
valeurs. Pour chaque résultat, dans l'ordre :

1. chaque valeur est donnée par son gabarit `start` quand l'état précédent n'a pas de valeur de ce nom (au premier
   tirage, ou quand le générateur a gagné une valeur), par son gabarit `next` sinon, ou gardée telle quelle quand elle
   n'a pas de `next` ;
2. les variables sont rendues, puis le corps, comme pour tout générateur.

Les gabarits de l'état voient l'état précédent comme `previous`, et comme `state` les valeurs données jusque-là
par-dessus les précédentes : une valeur voit la nouvelle valeur de celles écrites avant elle et la valeur précédente des
autres. Les variables et le corps voient le nouvel état comme `state` et le précédent comme `previous` ; au premier
tirage, `previous` est vide, et un gabarit le lit avec `|default`. Une valeur est un texte ; Twig lit un texte de
chiffres comme un nombre dans `state.day + 1`, et le générateur garde des nombres entiers avec `//` plutôt que `/`. Une
valeur de plus de 1024 caractères arrête le tirage.

Un générateur muni d'un état ne nomme aucune variable ni aucun champ `state` ou `previous`. Un autre générateur qui
l'appelle avec `generate` ou un champ `{generate: …}` obtient un résultat fait des gabarits `start` : l'état appartient
au tirage.

## Tirer

```php
use Tilttek\Generator\Draw;

$first = $generator->draw('travel', new Draw(count: 3));
$first->state;   // ['day' => '3', 'remaining' => '19', 'pace' => 'brisk']

$next = $generator->draw('travel', new Draw(state: $first->state));
$next->first();  // "Day 4: …"
```

Un tirage de plusieurs résultats fait passer l'état de l'un à l'autre : le deuxième résultat repart du premier. L'état
que retourne un tirage est celui de son dernier résultat. L'application peut aussi changer l'état entre les tirages (un
nouveau cap, un facteur de chaos monté par une scène, une destination plus lointaine), puisque ce n'est qu'une table de
textes ; une valeur que le générateur ne connaît pas est écartée, et une valeur que le générateur a mais que l'état n'a
pas repart de son départ. Un tirage avec un germe et un état donne chaque fois les mêmes résultats et le même état.

## Exemples

Le répertoire `examples/` contient trois tables à mémoire.

**La météo, en fleur d'hexagones** (`examples/weather.yaml`) : dix-neuf hexagones, chacun une météo, et une valeur
d'état `cell` qui dit dans lequel se trouve la météo. Chaque jour avance d'un pas dans une direction tirée selon le
poids, et reste en place au bord ; les entrées de `weather.cell` portent leurs voisines comme champs.

```yaml
state:
  cell:
    start: "10"
    next: "{{ pick('weather.cell', where: {cell: state.cell})[pick('weather.direction').text] }}"
templates: "{{ pick('weather.cell', where: {cell: state.cell})|upper_first }}"
```

**Un voyage** (`examples/travel.yaml`) : l'état compte les jours et les milles qui restent, que l'application fixe au
départ du voyage ; chaque tirage est une journée sur la route, et dit quelle distance le groupe a parcourue depuis la
veille : `{{ previous.remaining|default(state.remaining) - state.remaining }} miles covered`.

**Une exploration d'hexagones** (`examples/hexcrawl.yaml`) : l'état tient l'endroit où se trouve le groupe, en
coordonnées axiales `q` et `r`, le cap que fixe l'application, et le terrain de l'hexagone où il se trouve. Chaque
tirage entre dans l'hexagone suivant sur le cap, et son terrain suit le précédent par des étiquettes sur les entrées :
une forêt se poursuit en forêt plus souvent qu'elle ne devient lac.

```yaml
state:
  heading: se
  q: { start: "0", next: "{{ state.q + {n: 0, ne: 1, se: 1, s: 0, sw: -1, nw: -1}[state.heading]|default(0) }}" }
  r: { start: "0", next: "{{ state.r + {n: -1, ne: -1, se: 0, s: 1, sw: 1, nw: 0}[state.heading]|default(0) }}" }
  terrain: { start: plains, next: "{{ pick('hexcrawl.terrain', where: {from: state.terrain}) }}" }
```

Le même mécanisme donne des valeurs que l'application fixe et que le générateur ne fait que lire (une valeur sans
`next`) : le terrain des rencontres aléatoires, le lieu des rumeurs, et le facteur de chaos et les chances de l'oracle
du jeu en solo parmi les [tables aléatoires](tables.md).
