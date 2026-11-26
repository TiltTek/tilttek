<!-- phil-dev source-sha256=c4ae4342702072fd82777a93c7a1663e332ad9cb20b0035327fb9d3a62d72864 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau, sur le magasin en
mémoire et le `MockClock` de `symfony/clock` : une édition de son brouillon à son annonce, avec et sans demi-finale ;
des candidats proposés et ajoutés, admissibles ou non, au plus 100 ; les bulletins de chaque phase, remplacés, refusés
hors de leur phase, pour un votant écarté, ou parce qu'ils enfreignent les règles de leur phase ; les premiers
candidats ajoutés un à un ; les présélections et leurs égalités ; des finales gagnées par un candidat, par « aucun
prix », partagées par une égalité, ou sans bulletins ; des phases de moins de deux options ; des résultats scellés et
publiés ; des votants effacés dont les bulletins comptent toujours ; et les bornes de chaque valeur. Dans le monodépôt,
Infection maintient la bibliothèque à 100 % des mutants couverts.

## Tester du code qui gère des prix

Construisez le service sur le magasin en mémoire, et faites avancer l'édition dans ses phases :

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\FanAwards\Category;
use Tilttek\FanAwards\FanAwards;
use Tilttek\FanAwards\Store\InMemoryAwardStore;

$awards = new FanAwards(new InMemoryAwardStore(), new MockClock('2027-01-10 10:00:00', 'UTC'));
$awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel', method: 'borda')], semifinal: false);
$awards->advance('2027'); // nominations
```
