<!-- phil-dev source-sha256=3ec19c9f75b91893d4ac9e7ff9d5df017cd3fa56bfa87af10191da2889fe94d9 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, avec l'extension `mbstring`, dont les grammaires se servent pour lire les mots accentués.
- `twig/twig` 3.21 ou plus récent, dont le bac à sable compile et exécute les gabarits.
- `symfony/yaml` 8.2, qui lit les documents YAML.
- `tilttek/dice`, qui lit et lance les expressions de dés de `roll()`.

## Installer

```sh
composer require tilttek/generator
```

Rien n'est enregistré et rien n'est configuré : lisez un catalogue et construisez un `Generator` là où vous l'utilisez.
Une application Symfony installe `tilttek/generator-bundle`, qui lit les fichiers de sa configuration, les garde dans
le cache et construit le générateur.

```php
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Loading\FileLoader;

$generator = new Generator(new FileLoader()->catalog(['config/generators']), new Limits(picks: 200), locale: 'fr', cache: 'var/cache/generator');
```

Construire le générateur compile et vérifie chaque gabarit du catalogue ; construisez-le une fois et gardez-le pour la
requête ou le processus. Avec un répertoire de cache, Twig écrit les gabarits compilés dans des fichiers PHP de ce
répertoire et les charge, plutôt que de les évaluer avec `eval()`.
