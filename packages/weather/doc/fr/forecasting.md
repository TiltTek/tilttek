<!-- phil-dev source-sha256=002c0860d13854308d9e00d81a1fc59446eb4f352650da62146f11a4bb64a9fe -->
# Prévisions

## Un jour, puis le suivant

```php
use Tilttek\Weather\Weather;
use Tilttek\Weather\WeatherState;

$weather = new Weather(locale: 'en');
$forecast = $weather->forecast(new WeatherState('boreal', 'winter'), days: 3, seed: 'north-road 1');

foreach ($forecast->days as $day) {
    echo $day->label, ', ', $day->temperature, ' °C (', $day->fahrenheit(), ' °F)', "\n";
}
// hard frost, -24 °C (-11 °F)
// hard frost, -25 °C (-13 °F)
// hard frost, -26 °C (-15 °F)

$forecast->state->toArray();
// ['zone' => 'boreal', 'season' => 'winter', 'condition' => 'frost', 'temperature' => '-26']
```

`Weather::forecast($state, $days, $seed)` tire les jours suivants à partir d'un état et renvoie une `Forecast` : ses
`days`, dans l'ordre (`first()` et `last()`), et le `state` que laisse le dernier jour. Un `WeatherState` contient la
zone et la saison, que fixe l'application, ainsi que la condition et la température que le dernier jour a laissées ; un
état sans condition (le premier jour d'une campagne) part d'une condition tirée selon les poids de la saison et du
milieu de la plage de la saison, décalé par cette condition.

Un `Day` contient sa `condition`, son `label` dans la langue du `Weather`, sa `temperature` en degrés Celsius, sa
`zone` et sa `season` ; `fahrenheit()` donne la température en degrés Fahrenheit et `toArray()` donne le tout. Les
libellés de la zone et de la saison viennent des climats : `$weather->climates()->season($day->season)->labels['fr']`.

Le nombre de jours est borné par la limite de résultats de `tilttek/generator` (100 par défaut ; `new Weather(limits: new
Limits(results: 366))` pour une année), et une prévision de moins d'un jour est une `InvalidDefinition` de cette
bibliothèque.

## Les germes

Le même état et le même germe donnent les mêmes jours, dans n'importe quel processus : un germe fait de la campagne et
de la date (`'campaign-12 1472-03-04'`) fait que la météo d'un jour est la même chaque fois qu'on la demande. Sans
germe, c'est le `Randomizer` donné au `Weather` qui décide, un générateur sécurisé par défaut.

## Garder l'état

La bibliothèque ne garde rien. L'application stocke `$forecast->state->toArray()`, un tableau associatif de textes
courts, et le relit avec `WeatherState::fromArray()`, qui refuse un état sans zone ou sans saison, ou dont la
température n'est pas un nombre entier de degrés (`InvalidState`). Un état qui nomme une zone, une saison de la zone ou
une condition que les climats ne déclarent pas est une `UnknownClimate` quand on en fait la prévision.

## Saisons et voyages

Le calendrier du jeu est celui de l'application : quand il passe à la saison suivante, l'application change la saison
de l'état et garde le reste.

```php
$spring = $weather->forecast($winter->state->withSeason('spring'), days: 5);
```

La météo entre dans la nouvelle saison jour après jour : une condition que la nouvelle saison n'apporte pas devient
l'un de ses voisins qu'elle apporte, et une température hors de la nouvelle plage y revient de deux degrés par jour. Un
voyage vers un autre climat change la zone de la même façon, `withZone('arid', 'hot')`.

## Avec `tilttek/generator-bundle`

La météo est une table à mémoire de `tilttek/generator` ; la bibliothèque la livre sous forme de fichiers de
générateurs, écrits à partir de ses propres climats : `WeatherTable::directory('en')` et `WeatherTable::directory('fr')`
contiennent chacun un `weather.yaml`. Une application qui garde ses générateurs dans des fichiers en ajoute un aux
chemins de `tilttek/generator-bundle`, dont `MemoryTables` garde alors l'état par clé :

```yaml
tilttek_generator:
    paths:
        - '%kernel.project_dir%/config/generators'
        - '%kernel.project_dir%/vendor/tilttek/weather/generators/fr'
```

```php
$tables->set('weather', 'campaign-12', ['zone' => 'temperate', 'season' => 'autumn']);
$tables->draw('weather', 'campaign-12', count: 3, seed: 'campaign-12')->results;
// [['condition' => 'wind', 'label' => 'grand vent', 'temperature' => '8'], …]
```

Les résultats sont des tableaux de textes (`condition`, `label` et `temperature`), et l'état est celui de
`WeatherState`. Une application qui a ses propres climats écrit son fichier avec `WeatherTable::yaml($climates, 'fr')`,
ou construit un catalogue avec `WeatherTable::catalog($climates, 'fr', 'weather.north')` sous un autre nom.
