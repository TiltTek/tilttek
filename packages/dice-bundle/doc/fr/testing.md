<!-- phil-dev source-sha256=167927b43a3e318d657f3ce0c04478fe5d283e1410b2070c95c7fb8944f435bc -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

`tests/Unit` couvre la ligne du journal, `DiceRolls`, les deux journaux (celui de Doctrine sur SQLite en mémoire),
`DiceMacros` et les deux stockages de macros, la contrainte, le champ, l'extension Twig et le gabarit, et la
configuration. `tests/Functional` démarre un noyau avec FrameworkBundle, TwigBundle et DoctrineBundle sur SQLite, puis
lance, rejoue, produit le rendu et valide à travers le conteneur. Dans le monodépôt, Infection tient le bundle à 100 %
de mutants couverts.

## Tester une application qui lance des dés

Réglez `store: memory` dans l'environnement de test, et construisez `DiceRolls` avec un `Randomizer` doté d'un germe ou
scénarisé là où un test doit connaître les dés ; `MockClock` date les lancers.
