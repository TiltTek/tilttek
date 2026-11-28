<!-- phil-dev source-sha256=7cf849eb518bebba709813951cf1cfcbd2dfaa9471e9588c8eb6ea893e9df453 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau : les enregistrements et leurs refus, les textes par
locale et leurs valeurs de repli, les licences, les systèmes et leurs recherches, les localisations, le lecteur avec
chaque refus et son endroit, l'écriture et l'aller-retour par YAML et JSON, l'attribution en texte et en HTML,
l'importateur avec les quatre profils et un profil propre, et les paquets de données écrits, relus et refusés — un
document modifié, hors du paquet, un autre système, une autre version ou une autre source, un manifeste illisible —, et les paquets de données installés,
listés et chargés, depuis une liste propre et depuis celle de Composer, remplacée dans un processus à part.
Les extraits et documents qu'elle lit sont les petits fichiers de `examples/`, écrits pour les tests. Dans le
monodépôt, les paquets de données publiés — le SRD 5.1, le SRD 5.2.1 et Fate Core, gardés dans son répertoire `data/`
— sont relus par le lecteur strict, leurs enregistrements comptés et leurs mentions comparées à celles du moteur ; une
copie de la bibliothèque seule saute ces tests. Chaque paquet de données a ses propres tests, qui le chargent par
`InstalledDataPackages`. Infection maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/document.php` est la cible de fuzzing du lecteur : tout texte YAML est refusé avec les exceptions de la
bibliothèque ou donne un système que l'écrivain écrit et que le lecteur relit. `composer fuzz` l'exécute.

`tests/Benchmark/ReaderBench.php` mesure la lecture d'un système de 1 000 termes depuis YAML et depuis JSON, la
recherche d'un terme, le listage d'une catégorie, et le rendu de l'attribution d'une page.

## Tester du code qui lit des systèmes

Construisez un système en code, ou lisez un petit document à vous avec `SystemReader`, et donnez-le au code testé :

```php
$system = new SystemReader()->system([
    'format' => SystemReader::FORMAT,
    'system' => ['key' => 'test', 'names' => 'Test'],
    'source' => ['title' => 'Test', 'licence' => 'owned'],
    'attributes' => [['key' => 'grit', 'names' => 'Grit', 'minimum' => 1, 'maximum' => 6]],
]);
```
