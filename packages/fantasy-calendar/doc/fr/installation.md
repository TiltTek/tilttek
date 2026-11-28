<!-- phil-dev source-sha256=334f112bf668c67eac71a5c46ca8a72fb5c217d0a2a58f157a177cc689b6c90d -->
# Installation

```sh
composer require tilttek/fantasy-calendar
```

La bibliothèque exige PHP 8.5 ou une version plus récente avec l'extension `mbstring`, et aucun autre paquet. Ses
calendriers, ses jours et ses dates sont des valeurs immuables, qu'on peut partager sans risque entre les requêtes d'un
*worker* ; `Store\InMemoryReminders`, gardé pour les tests, est le seul type qui ait un état.
