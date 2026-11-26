<!-- phil-dev source-sha256=76fe6271b680fdc4defc0bc611655afa99635434a702f7799361b40104fc03ad -->
# tilttek/predictions

**Des prédictions sur le résultat daté d'événements, réglées en points : rien n'est misé, et une mauvaise réponse ne coûte rien.**

tilttek/predictions permet à une application de poser à ses membres une question sur un résultat daté — qui gagne la
finale, si la campagne se termine avant son échéance —, de recevoir leurs choix jusqu'à l'échéance, puis de verser
les bonnes réponses en points de `tilttek/points` une fois le résultat déclaré. Une question est notée par choix — le
membre choisit un résultat, et un bon choix rapporte les points de la question —, ou par probabilité : le membre donne
une probabilité à chaque résultat, et gagne une part des points qui croît à mesure que le score de Brier de sa
prévision baisse, ce qui récompense une confiance calibrée plutôt qu'un pari audacieux.

Rien n'est jamais misé, pas même en monnaie fictive : un membre ne donne jamais de points pour prédire, et la
bibliothèque ne tient ni marché ni cotes. Une question porte sur un résultat d'un événement que l'application déclare,
jamais sur une personne : ses résultats sont les clés que l'application donne aux issues de l'événement, qui ne
peuvent pas être la référence d'un membre.

Le règlement est versé une seule fois, quoi qu'il arrive : les points de chaque prédiction sont écrits sous une clé
construite à partir de son identifiant aléatoire, que le grand livre n'écrit qu'une fois, de sorte qu'un règlement
relancé, ou lancé par deux processus à la fois, ne verse rien en double ; une question annulée ne verse rien. Effacer un
membre supprime ses prédictions.

Elle s'adresse à une communauté qui veut des prévisions amicales sur ses finales, ses concours et ses campagnes sans
les transformer en paris. C'est une bibliothèque PHP simple dont les dépendances sont `tilttek/points`, `phil/resource`
et `psr/clock`.

## État

| Partie | État |
|---|---|
| `Predictions` : poser, prédire jusqu'à l'échéance, résoudre, régler, annuler, lire, effacer | Implémenté et testé |
| `Question`, `Pick`, `Prediction`, `Brier`, `Settlement` | Implémenté et testé |
| `Contract\PredictionStore`, avec `Store\InMemoryPredictionStore` | Implémenté et testé |
| Un magasin sur Doctrine DBAL, et un bundle Symfony | Non commencé |
| Des tableaux de tournoi remplis d'avance | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Poser et régler](doc/fr/predictions.md) | Les questions, les choix, l'échéance, la notation, le règlement versé une fois, l'annulation, l'effacement. |
| [Notation](doc/fr/scoring.md) | Le choix et la probabilité, le score de Brier en nombres entiers, des valeurs calculées. |
| [Points d'extension](doc/fr/extension-points.md) | Le magasin, la devise, la source et la taille des lots. |
| [Exceptions](doc/fr/exceptions.md) | `PredictionsException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, et le test du code qui pose des questions. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/predictions
```

```php
use Tilttek\Predictions\Pick;
use Tilttek\Predictions\Predictions;
use Tilttek\Predictions\Store\InMemoryPredictionStore;

$predictions = new Predictions(new InMemoryPredictionStore(), $points, $clock);

$predictions->ask('final:2026', ['home', 'away'], new DateTimeImmutable('2026-12-05 19:00 UTC'), 50);
$predictions->predict('final:2026', $member, Pick::choice('home'));
$predictions->resolve('final:2026', 'home');    // 50 points to each right pick, once
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
