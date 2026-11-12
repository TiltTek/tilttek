<!-- phil-dev source-sha256=769584d84f6c18a7bd039c5caed0ae475b1b54d1478d638028806e6b0e543c96 -->
# tilttek/weather

**La météo dans le monde de jeu, jour après jour, à la suite de la veille.**

tilttek/weather donne la météo du monde d'une campagne : à partir de l'état que le dernier jour a laissé (la zone
climatique, la saison, l'état du ciel et la température), il tire les jours suivants et l'état qu'ils laissent. Un jour
suit le précédent : une condition reste, aussi souvent que la persistance de la zone le dit, ou devient l'un de ses
voisins que la saison apporte (le beau temps se couvre, un ciel couvert tourne à la bruine, puis à la pluie), jamais du
ciel dégagé au blizzard en une nuit ; la température bouge de quelques degrés par jour vers ce que la saison et la
condition demandent, et reste dans la plage de la saison. Quand l'application fait passer le calendrier à la saison
suivante, la météo y entre jour après jour. Les saisons, les zones climatiques et les conditions sont des données
déclarées, en YAML : cinq zones sont livrées (tempérée, boréale, méditerranéenne, aride et tropicale), avec seize
conditions libellées en anglais et en français, et une application ajoute ses propres mondes. Le même état et le même
germe donnent les mêmes jours.

Il s'adresse à tout code PHP qui a besoin de la météo d'un monde : la boîte à outils d'un meneur de jeu, le journal
d'une campagne, un voyage ou une exploration en hexagones qui demande ce que fait le ciel chaque jour, une table
virtuelle. Il ne garde rien : l'application stocke l'état (par campagne, par table) et le repasse, ou laisse
`tilttek/generator-bundle` le garder.

C'est une bibliothèque PHP sur `tilttek/generator` : la météo est une table à mémoire de cette bibliothèque, écrite à
partir des climats déclarés, de sorte qu'elle tire avec les germes, les limites et le bac à sable du générateur, et se
charge comme fichier de générateur dans une application qui garde ses générateurs dans des fichiers. La variation
quotidienne de la température est un lancer de `tilttek/dice`, vérifié quand les climats sont lus. `symfony/yaml` lit
les climats.

La conception est petite : un `ClimateReader` lit des `Climates` (`Condition`, `Season` et `Zone`, avec ce que chaque
`SeasonalClimate` apporte), `WeatherTable` les écrit comme un catalogue ou un fichier YAML de `tilttek/generator`, et
`Weather` tire une `Forecast` de `Day` à partir d'un `WeatherState`.

## État

| Partie | État |
|---|---|
| Climats déclarés : conditions avec leurs libellés, leur température et leurs voisins, saisons, zones avec leurs plages saisonnières, leurs poids, leur persistance et leur lancer quotidien ; chaque référence vérifiée | Implémenté et testé |
| Climats lus depuis du YAML, chaque problème avec son endroit ; les climats livrés en anglais et en français | Implémenté et testé |
| La météo comme table à mémoire de `tilttek/generator` : transitions par les voisins, persistance, températures dans la saison, nouvelle saison atteinte jour après jour | Implémenté et testé |
| `Weather` : prévisions à partir d'un état, libellés dans une langue, reproductibles à partir d'un germe, dans les limites du générateur | Implémenté et testé |
| Fichiers de générateurs en anglais et en français, pour une application qui charge ses générateurs depuis des fichiers | Implémenté et testé |
| Exceptions sous une seule interface `WeatherException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Prévisions](doc/fr/forecasting.md) | `Weather`, `WeatherState`, `Forecast` et `Day` ; germes, saisons, voyages ; garder l'état avec `tilttek/generator-bundle`. |
| [Climats](doc/fr/climates.md) | Le document YAML des climats, les zones livrées, et comment un jour suit le jour d'avant. |
| [Points d'extension](doc/fr/extension-points.md) | Les climats, le hasard et les limites qu'une application donne. |
| [Exceptions](doc/fr/exceptions.md) | `WeatherException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, et comment tester du code qui fait des prévisions. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/weather
```

```php
use Tilttek\Weather\Weather;
use Tilttek\Weather\WeatherState;

$weather = new Weather(locale: 'fr');

$today = $weather->forecast(new WeatherState('temperate', 'autumn'), seed: 'campaign-12 day-1');
$today->first()->label;          // "gelée blanche"
$today->first()->temperature;    // 6

$week = $weather->forecast($today->state, days: 7, seed: 'campaign-12 day-2');
$store->save($week->state->toArray());   // the application keeps the state for the next day
```

## Prérequis

PHP 8.5 ou une version plus récente, avec l'extension `mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
