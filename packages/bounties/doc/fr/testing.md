<!-- phil-dev source-sha256=062deb3d7d64d2d44c19f44bdcf37dd49eab5828530ea2eada6cdad9b9fd565a -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : chaque mouvement de la machine à états
depuis chaque état, au dernier moment où il est permis et au premier où il ne l'est pas ; les limites de réclamations et
d'attributions ; les montants supérieurs à ce qui reste ; les attributions partagées, fermées ou réglées à la fin du
délai de grâce ; les mouvements refaits ; une offre, un mouvement et un règlement devancés par un autre auteur ; un
séquestre qui échoue une fois et est réglé de nouveau ; les références du genre `erased` refusées à chaque point
d'entrée, sans rien changer ; le contrat du séquestre, exécuté sur le séquestre en mémoire et sur le registre de
`tilttek/points` ; les enregistrements refusés champ par champ et dans leur ensemble. Dans le monodépôt, Infection
maintient la bibliothèque à 100 % des mutants couverts.

Deux cibles de fuzzing s'exécutent avec `composer fuzz` :

- `tests/Fuzz/record.php`, `bounty-record` : tout texte JSON donne une prime qui s'écrit comme elle a été lue, ou est
  refusé avec `InvalidBounty`, jamais avec une autre exception.
- `tests/Fuzz/lifecycle.php`, `bounty-lifecycle` : chaque octet de l'entrée est un mouvement de l'un de quatre membres ou
  d'une référence du genre `erased` ; quels que soient les mouvements, aucun point n'est créé ni perdu, les points sont
  détenus exactement tant que la prime est ouverte, les gagnants sont payés, les attributions ne dépassent jamais le
  montant, un mouvement d'une référence `erased` est refusé avec `InvalidBounty` et ne change rien, et la prime se
  relit comme elle a été écrite.

`tests/Benchmark/BountiesBench.php` mesure, sur une prime de 200 réclamations et de 19 attributions, une attribution
envoyée de nouveau — la prime relue depuis son enregistrement et vérifiée —, et l'enregistrement relu et vérifié : moins
d'une milliseconde chacun avec OPcache.

## Tester une application

Donnez à `Bounties` les implémentations en mémoire et une horloge qui ne bouge pas :

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Bounties\Escrow\InMemoryBountyEscrow;
use Tilttek\Bounties\Store\InMemoryBountyStore;

$escrow = new InMemoryBountyEscrow();
$escrow->fund($ana, 1000);
$clock = new MockClock('2026-11-13 20:00 UTC');
$bounties = new Bounties(new InMemoryBountyStore(), $escrow, $dispatcher, clock: $clock);

$clock->sleep(9 * 86_400);
$bounties->expireDue();
```

Un magasin à soi passe les mêmes mouvements que `InMemoryBountyStore` et rend les mêmes enregistrements.
