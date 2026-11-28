<!-- phil-dev source-sha256=f3b7429a2997cc75af6a06f6cb60ee619ba73dffae4cca9ec8f4401ed265e53c -->
# Installation

```sh
composer require tilttek/grid
```

La bibliothèque exige PHP 8.5 ou une version plus récente et aucun autre paquet. Elle ne garde aucun état : chaque type
est une valeur immuable, qu'on peut partager sans risque entre les requêtes d'un *worker*.
