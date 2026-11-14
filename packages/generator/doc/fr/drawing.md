<!-- phil-dev source-sha256=39797b2a9dcef1fcc34e5bac8dbcfd2ce95d01393ccc73aac02010c2228b28a7 -->
# Tirages

`Generator::draw()` prend le nom d'un générateur et un `Draw`, et retourne un `DrawResult` :

```php
use Tilttek\Generator\Draw;

$result = $generator->draw('name.elf', new Draw(count: 5, unique: true, seed: 'table 7'));

$result->results;     // list of texts, structured results or maps
$result->first();     // the first result, or null
$result->isShort();   // fewer results than asked
$result->toArray();   // ['generator' => …, 'results' => […], 'requested' => 5, 'short' => false]
```

## Attributions

`DrawResult::$attributions` liste l'`Attribution` de chaque liste, entrée et générateur que les résultats ont utilisés
(une fois chacune, dans l'ordre où le tirage les a utilisés pour la première fois), pour que l'application affiche la
source, la licence et l'attribution de ce qu'elle imprime. L'attribution propre d'une entrée prend la place de celle de
sa liste ; un mot qu'invente une liste de Markov porte celle de sa liste. `toArray()` les ajoute sous `attributions`,
chacune avec les textes qu'elle a, quand il y en a :

```php
$result->toArray()['attributions'];   // [['source' => 'Old tables', 'licence' => 'CC-BY-4.0'], …]
```

Voyez [Listes et générateurs](lists-and-generators.md#dou-vient-le-texte).

## Un état d'un tirage au suivant

Un générateur muni d'un état (une table à mémoire) prend l'état que son dernier tirage a laissé, et retourne le
nouveau :

```php
$result = $generator->draw('weather', new Draw(count: 7, state: $saved));

$result->state;       // ['cell' => '13'], to keep for the next draw
$result->toArray();   // […, 'state' => ['cell' => '13']]
```

L'état d'un tirage est une table d'au plus `Draw::STATE_VALUES` (32) noms vers des textes d'au plus
`Draw::STATE_LENGTH` (1024) caractères, en UTF-8 comme le germe ; les nombres, les booléens et les objets qui
s'affichent se lisent comme des textes, tout le reste est une `InvalidDefinition`. La bibliothèque ne garde aucun état : l'application le tient entre
les tirages, par campagne, par table ou par personne, et le repasse. Un générateur sans état ignore celui qu'apporte un
tirage, et son résultat n'a pas d'état. Voyez [Tables à mémoire](memory.md).

## Résultats uniques

Un tirage unique (comme `generate('name', 3)` dans un gabarit et un champ `{generate: name, count: 3}`) produit des
résultats l'un après l'autre jusqu'à en avoir assez de différents, en les comparant en entier, ou jusqu'à avoir épuisé
ses tentatives : `Limits::$attempts` fois le nombre demandé. Il ne boucle jamais sans fin quand les listes n'en ont pas
assez : il retourne ce qu'il a trouvé, et `isShort()` le signale. Un tirage unique d'un générateur muni d'un état
écarte un résultat qu'il a déjà, avec l'état que ce résultat a laissé.

## Hasard et germes

Un tirage sans germe utilise le `Randomizer` du générateur : un moteur sûr par défaut, un moteur scénarisé ou doté d'un
germe dans les tests. Un tirage avec germe utilise `Xoshiro256StarStar` initialisé avec le SHA-256 du germe. Le même
germe, le même catalogue et la même version de PHP donnent les mêmes résultats, ce qui permet à un site de rejouer un
tirage ou de le partager par son germe.

## Limites

`Limits` arrête un générateur emballé ; au-delà d'une limite, le tirage échoue avec `LimitExceeded`.

| Limite | Valeur par défaut | Ce qu'elle borne |
|---|---|---|
| `depth` | 8 | Les générateurs appelés les uns dans les autres, celui qui est tiré compris : un générateur qui s'appelle lui-même s'arrête. |
| `picks` | 1000 | Les tirages dans les listes, les lancers, `between`, les appels, les passages supplémentaires et les essais d'un mot inventé d'un résultat. |
| `milliseconds` | 250 | Le temps passé sur un résultat, vérifié à chaque tirage dans une liste, lancer, appel et pas, et avant le rendu de chaque gabarit. |
| `attempts` | 10 | Les tentatives par résultat demandé à un tirage unique. |
| `results` | 100 | Les résultats demandés à un tirage. |
| `cells` | 2500 | Les cases d'une carte ou les nœuds d'un graphe. |
| `drawMilliseconds` | 1000 | Le temps passé sur tout le tirage (chaque résultat, et chaque tentative d'un tirage unique), vérifié comme le temps d'un résultat. |
| `steps` | 10000 | Les tours de chaque boucle `for` et les affectations `{% set %}` d'un résultat. |
| `bytes` | 65536 | Le texte le plus long, en octets d'UTF-8, que `replace` ou `join` retourne, mesuré avant qu'il soit fait. |
| `megabytes` | 16 | La mémoire dont un résultat peut croître, vérifiée à chaque pas. |

Chaque gabarit est compilé à la construction du générateur : la limite de temps ne compte que le tirage, et non la
première compilation d'un gabarit. Sans répertoire de cache (`$cache`, le dernier argument de `Generator`), Twig charge
chaque gabarit compilé par `eval()` ; avec un répertoire, il écrit chaque gabarit dans un fichier PHP de ce répertoire
et charge le fichier, que le cache d'opcodes garde ensuite. Une application de longue durée lui donne un répertoire.

Une valeur d'état plus longue que `Draw::STATE_LENGTH` caractères arrête aussi le tirage avec `LimitExceeded`, puisque
le tirage suivant ne pourrait pas la rapporter.

Le temps se lit sur un `Timer`, par défaut `SystemTimer`, l'horloge monotone du système. Les limites de temps sont
vérifiées avant le rendu de chaque gabarit (chaque case d'une carte, chaque champ, chaque valeur d'un état), à chaque
tirage dans une liste et à chaque pas dans un gabarit : une boucle s'arrête au tour qui franchit une limite ([Le budget
d'un résultat](templates.md#le-budget-dun-résultat)). Le temps du tirage borne un tirage de nombreux résultats, ou un
tirage unique qui retombe sans cesse sur le même : vingt cartes demandées à un tirage unique s'arrêtent après une
seconde au lieu de tourner pendant dix.
