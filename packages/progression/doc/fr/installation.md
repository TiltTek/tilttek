<!-- phil-dev source-sha256=ec8a802bc53de10740ad472e7f075aa6cc8cddfdc905b6b6e8e2775286474694 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent.
- `tilttek/activity`, le journal de l'activité des membres que lisent les règles et les séries.
- `phil/resource`, la référence opaque d'un membre ou d'un objet (`member:44`, `character:9`).
- `psr/clock`, l'interface de l'horloge ; toute horloge PSR-20 convient, comme `symfony/clock`.

## Installer

```sh
composer require tilttek/progression
```

Rien n'est enregistré et rien n'est configuré : construisez les magasins, `StreakKeeper`, `ProgressionProjection` et
`Progression` là où vous les utilisez. Une application Symfony installe `tilttek/progression-bundle`, qui garde la
progression dans Doctrine DBAL et construit tout à partir de sa configuration.

```php
use Tilttek\Progression\Progression;
use Tilttek\Progression\ProgressionProjection;
use Tilttek\Progression\Store\InMemoryExperienceStore;
use Tilttek\Progression\Store\InMemoryMilestoneStore;
use Tilttek\Progression\Store\InMemoryStreakStore;
use Tilttek\Progression\Streak\StreakKeeper;

$experience = new InMemoryExperienceStore();
$milestones = new InMemoryMilestoneStore();
$streakStore = new InMemoryStreakStore();
$keeper = new StreakKeeper($streaks, $streakStore, $observer);
$projection = new ProgressionProjection($tracks, $keeper, $experience, $milestones, $clock, observer: $observer);
$progression = new Progression($tracks, $keeper, $experience, $milestones, $streakStore, $clock);
```

Remettez la projection aux `LiveProjections` ou au `Projector` de `tilttek/activity`, pour qu'elle lise le journal. Les
magasins en mémoire oublient tout à la fin du processus : ils servent aux tests, aux scripts et aux exemples. Une
application qui garde sa progression écrit des magasins sur son stockage ([Écrire un magasin](writing-a-store.md)) ou
installe le bundle.
