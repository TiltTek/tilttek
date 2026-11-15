<!-- phil-dev source-sha256=8a38bca7056ea0a913f395f9cba61d401d76c03611e5589736f48061f0721c55 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : chaque mouvement de la machine à états
depuis chaque état, au dernier moment où il est permis et au premier où il ne l'est pas ; les limites de participants, de
preuves et d'invités ; les votes, les votes changés et les votes nécessaires ; les mouvements refaits ; une création,
un mouvement et une expiration devancés par un autre auteur ; un séquestre qui échoue une fois et est réglé de nouveau ;
les références du genre `erased` refusées à chaque point d'entrée, sans rien changer ; le contrat du séquestre, exécuté
sur le séquestre en mémoire et sur le registre de `tilttek/points` ; les enregistrements refusés champ par champ et dans
leur ensemble. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

Deux cibles de fuzzing s'exécutent avec `composer fuzz` :

- `tests/Fuzz/record.php`, `challenge-record` : tout texte JSON donne un défi qui s'écrit comme il a été lu, ou est
  refusé avec `InvalidChallenge`, jamais avec une autre exception.
- `tests/Fuzz/lifecycle.php`, `challenge-lifecycle` : chaque octet de l'entrée est un mouvement de l'un de six membres ou
  d'une référence du genre `erased` ; quels que soient les mouvements, aucun point n'est créé ni perdu, le lot est
  détenu exactement tant que le défi est ouvert ou gelé, un gagnant est payé, un mouvement d'une référence `erased` est
  refusé avec `InvalidChallenge` et ne change rien, et le défi se relit comme il a été écrit.

`tests/Benchmark/FriendChallengesBench.php` mesure, sur un défi qui contient le maximum permis — 100 participants et 200
preuves —, une preuve rejetée, relue depuis son enregistrement et réécrite, et l'enregistrement relu et vérifié :
environ 1,5 ms chacun avec OPcache.

## Tester une application

Donnez à `FriendChallenges` les implémentations en mémoire et une horloge qui ne bouge pas :

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\FriendChallenges\Escrow\InMemoryPrizeEscrow;
use Tilttek\FriendChallenges\Store\InMemoryChallengeStore;
use Tilttek\FriendChallenges\Store\InMemoryFriendship;
use Tilttek\FriendChallenges\Store\InMemoryModeration;

$escrow = new InMemoryPrizeEscrow();
$escrow->fund($ana, 1000);
$friends = new InMemoryFriendship();
$friends->befriend($ana, $bea);
$clock = new MockClock('2026-11-13 20:00 UTC');
$challenges = new FriendChallenges(new InMemoryChallengeStore(), $escrow, $friends, new InMemoryModeration($moderator), $dispatcher, clock: $clock);

$clock->sleep(11 * 86_400);
$challenges->expireDue();
```

Un magasin à soi passe les mêmes mouvements que `InMemoryChallengeStore` et rend les mêmes enregistrements.
