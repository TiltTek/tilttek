<!-- phil-dev source-sha256=484bcccb1203f7a189756acd2ab30ebe16547042ba0351ebf58f9839727a395f -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau, sur le dépôt en mémoire
et le `MockClock` de `symfony/clock` : des documents créés, modifiés et supprimés aux limites de leurs bornes ; des
titres, des textes, des pièces jointes et des types de média refusés ; des révélations à toute la table et à des
joueurs, de nouveau à une nouvelle révision, aux limites des audiences ; cacher à toute la table et à des joueurs ; des
révélations planifiées à un moment et sur un signal, remplacées, retirées, déclenchées par lots, la plus ancienne
d'abord, terminées par une révélation ; des vues une fois par révision ; la vue du joueur avec sa fraîcheur et son
ordre ; les vues du meneur de jeu ; l'effacement d'un joueur ; l'ordre dans lequel chaque liste est lue ; et les
événements communiqués. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

## Tester le code qui utilise des documents

Construisez le service sur le dépôt en mémoire et une horloge que le test déplace :

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Handouts\Audience;
use Tilttek\Handouts\Handouts;
use Tilttek\Handouts\Store\InMemoryHandoutStore;
use Tilttek\Handouts\Trigger;

$clock = new MockClock('2026-12-04 19:00:00', 'UTC');
$handouts = new Handouts(new InMemoryHandoutStore(), $clock);

$handouts->create($letter, $table, 'The miller\'s letter', 'Meet me at the mill.');
$handouts->stage($letter, Audience::everyone(), Trigger::at($clock->now()->modify('+1 hour')));
$clock->modify('+1 hour');
$handouts->releaseDue();
```

Un `Contract\HandoutObserver` qui garde les événements qu'il reçoit permet à un test de lire ce qui a été communiqué.
Le dépôt d'une application réussit les mêmes tests quand il remplace le dépôt en mémoire dans une copie de la suite.
