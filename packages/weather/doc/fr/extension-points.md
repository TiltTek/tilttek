<!-- phil-dev source-sha256=bf5b00f107212d0f9f178e47f567c953cca785e16fa91d0bc98ecd38564f5baf -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

`Weather` reçoit ses collaborateurs dans son constructeur ; chacun a une valeur par défaut et peut être remplacé.

| Collaborateur | Par défaut | Usage |
|---|---|---|
| `Tilttek\Weather\Climates` | `Climates::default()` | Les climats du monde : lus par `ClimateReader` dans le YAML de l'application, ou construits en PHP. |
| la langue | `en` | La langue des libellés, l'une des `locales` des climats. |
| `Random\Randomizer` | un moteur sécurisé | Le hasard d'une prévision sans germe : un moteur à germe dans les tests. |
| `Tilttek\Generator\Limits` | les limites par défaut | Les bornes d'un tirage de `tilttek/generator` ; `results` borne les jours d'une prévision. |

`WeatherTable` donne la météo à une application qui tire elle-même avec `tilttek/generator` : `catalog()` sous forme de
`Catalog`, sous un nom au choix, et `yaml()` sous forme de fichier de générateur.
