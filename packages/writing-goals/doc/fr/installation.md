<!-- phil-dev source-sha256=c0f084dce17454a78922b4e4543c851b57bf4df3b09ae3a3d22a3b7e79dc5882 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `phil/resource`, la référence opaque d'un auteur.
- `psr/clock`, l'horloge où se lit le jour d'aujourd'hui de l'auteur, et `psr/event-dispatcher`, qui informe
  l'application d'un objectif atteint. Une application Symfony passe ses services `clock` et `event_dispatcher`.

## Installer

```sh
composer require tilttek/writing-goals
```

## Brancher la bibliothèque à la main

Deux magasins, une horloge et un répartiteur font `WritingGoals` :

```php
use Symfony\Component\Clock\Clock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\WritingGoals\Store\{InMemoryGoalStore, InMemoryLogStore};
use Tilttek\WritingGoals\WritingGoals;

$goals = new WritingGoals(new InMemoryGoalStore(), new InMemoryLogStore(), new Clock(), new EventDispatcher());
```

`Planner` calcule un plan à partir d'un objectif et de son avancement sans aucun magasin, pour une application qui garde
les deux elle-même :

```php
$plan = new Planner()->plan($goal, Progress::fromArray(['2026-11-01' => 1800]), Day::fromString('2026-11-02'));
```

Les magasins en mémoire oublient tout avec le processus : une application écrit les siens sur son stockage
([Écrire un magasin](writing-a-store.md)).
