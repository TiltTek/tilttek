<!-- phil-dev source-sha256=277eb5b305cd31ea3af1d9f64a92b9ed78f971dd8a35a411b7d301a8966f3e94 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau, sur le dépôt en mémoire
et le `MockClock` de `symfony/clock` : l'ouverture, le renommage, le changement de système et la fermeture, à la limite
des inventaires qu'un propriétaire détient ; les objets ajoutés, augmentés, diminués et retirés, aux limites de leurs
quantités et des objets qu'un inventaire détient ; les lieux, les contenants et leurs limites de poids et
d'emplacements, les contenants emboîtés, les contenus qui ne pèsent rien, la limite de profondeur et les cycles ; le
déplacement d'une pile entière ou en partie, l'équipement, le transfert ; les encombrements par poids et par piles
d'emplacements ; les règles de transport, leurs paliers et leurs fractions, les capacités venues du porteur, les charges
et les refus au-delà de la capacité ; le journal, son ordre, sa limite et ses raisons ; l'effacement d'une référence ;
l'ordre dans lequel sont lus les objets, les inventaires et les entrées du journal ; et les événements communiqués. Dans
le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

## Tester le code qui utilise des inventaires

Construisez le service sur le dépôt en mémoire et sur une horloge que le test fait avancer :

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Inventory\Inventories;
use Tilttek\Inventory\Rules\StaticRuleBook;
use Tilttek\Inventory\Store\InMemoryInventoryStore;

$clock = new MockClock('2026-12-01 19:00:00', 'UTC');
$inventories = new Inventories(new InMemoryInventoryStore(), $clock, new StaticRuleBook(['srd-5.2' => $rules]));

$inventories->open($inventory, $character, 'Gear', 'srd-5.2');
$clock->modify('+1 hour');
$inventories->add($inventory, 'rope', 'Rope');
```

Un `Contract\InventoryObserver` qui garde les événements qu'il reçoit permet à un test de lire ce qui a été communiqué ;
un `Contract\CarrierCapacity` qui lit une table donne à chaque porteur la capacité dont un test a besoin. Un dépôt de
l'application réussit les mêmes tests quand il remplace le dépôt en mémoire dans une copie de la suite.
