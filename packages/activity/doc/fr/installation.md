<!-- phil-dev source-sha256=cdebe3c1ac7dfd779470c201a553eb9b98168c3ede4d524a44d3a12714a9a43c -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent.
- `phil/resource`, la référence opaque d'un membre ou d'un objet (`member:44`, `game:812`).
- `psr/clock`, l'interface de l'horloge qui date chaque entrée. Toute horloge PSR-20 convient, comme `symfony/clock`.

## Installer

```sh
composer require tilttek/activity
```

Rien n'est enregistré et rien n'est configuré : construisez un `Journal` sur un `ActivityLog` là où vous l'utilisez.
Une application Symfony installe `tilttek/activity-bundle`. Celui-ci garde le journal dans Doctrine DBAL et construit
le `Journal`, le `Projector` et les projections à partir de sa configuration.

```php
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Verbs;

$journal = new Journal(new InMemoryActivityLog(), $clock, Verbs::of(['news.commented', 'game.*']));
```

`InMemoryActivityLog` oublie tout à la fin du processus : il sert aux tests, aux scripts et aux exemples. Une
application qui conserve son journal écrit un `ActivityLog` sur son stockage ([Écrire un journal](writing-a-journal.md))
ou installe le bundle.
