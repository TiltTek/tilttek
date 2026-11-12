<!-- phil-dev source-sha256=fc0c9f68ac6b4befc407d02ff19ba7693f05165da3888da7946d02bcc08714ec -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec l'extension `mbstring`, qui mesure les libellés.
- `tilttek/generator`, dont les tables à mémoire tirent la météo.
- `tilttek/dice`, qui vérifie et lance la variation quotidienne de la température.
- `symfony/yaml` 8.2, qui lit les climats.

## Installation

```sh
composer require tilttek/weather
```

Rien n'est enregistré et rien n'est configuré : construisez un `Weather` là où vous l'utilisez.

```php
use Tilttek\Weather\Climates;
use Tilttek\Weather\ClimateReader;
use Tilttek\Weather\Weather;

$shipped = new Weather(locale: 'en');
$ownWorld = new Weather(new ClimateReader()->file('config/climates.yaml'), 'fr');
```

Construire un `Weather` lit les climats, écrit la table à mémoire et la vérifie comme `tilttek/generator` vérifie
n'importe quel catalogue ; construisez-le une fois et gardez-le pour la requête ou le processus. Il ne garde rien entre
deux prévisions : une seule instance sert toutes les campagnes d'un processus de longue durée.
