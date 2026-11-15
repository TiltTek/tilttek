<!-- phil-dev source-sha256=611359670dc1ffa2d959d3add3bd30befea5eb70b00167ce768c70c06b187ab1 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : des octrois, des dépenses et des
transferts vérifiés selon les règles — le plafond, un solde sous zéro, qui en a le droit — ; des opérations envoyées de
nouveau et des identifiants réutilisés ; des sessions avec leur conservation et leur rafraîchissement, sorte par sorte ;
un autre écrivain qui atteint le registre le premier, une, deux et trois fois ; des règles et des entrées de registre
refusées champ par champ et relues telles qu'elles ont été écrites ; un membre effacé sous un pseudonyme, dans chaque
table, les soldes et les positions inchangés, une seule fois. Dans le monodépôt, Infection maintient la
bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/rules.php` est la cible de fuzzing des règles d'une table : n'importe quel texte donne des règles dont les
rafraîchissements restent entre 0 et le plafond et qui s'écrivent comme elles ont été lues, ou est refusé avec
`InvalidTokenRule`, jamais avec une autre exception. `composer fuzz` l'exécute.

`tests/Benchmark/LuckTokensBench.php` mesure un jeton dépensé puis octroyé de nouveau après 10 000 entrées, et le début
d'une session pour douze joueurs.

## Tester une application

Donnez à `LuckTokens` les stockages en mémoire et une horloge qui ne bouge pas :

```php
$tokens = new LuckTokens($rules, new InMemoryTokenLedger(), $dispatcher, clock: new MockClock('2026-11-13 20:00 UTC'));
```

Un test d'un stockage de votre cru exécute les mêmes opérations sur lui et sur `InMemoryTokenLedger`, et compare les
registres avec `LedgerEntry::toArray()`.
