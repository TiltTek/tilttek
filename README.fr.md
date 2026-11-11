# Tilttek

**Des bibliothèques PHP et des bundles Symfony ouverts pour les jeux et la ludification : dés, butin, points, niveaux,
badges, quêtes et collections.**

Tilttek est un ensemble de petits paquets `tilttek/*` qui font chacun une seule chose : une bibliothèque en PHP pur, le
plus souvent accompagnée d'un bundle Symfony qui la branche à la configuration, à Doctrine, à Twig, à la console et à
Messenger. Ils suivent les règles des paquets [`phil/*`](https://github.com/parler-haut-interagir-librement/phil) de
PHIL : typés pour PHPStan à son niveau maximal et testés jusqu'à la mutation de chaque ligne de leur domaine.

Ce dépôt est le **monodépôt** où tous les paquets sont développés, testés et documentés ensemble. Chaque paquet est
ensuite copié dans son propre dépôt, en **lecture seule**, d'où Composer l'installe par Packagist.

## Paquets

### Dés et butin

| Paquet | Description |
|---|---|
| [`tilttek/dice`](https://github.com/TiltTek/dice) | La notation des dés lue, vérifiée et lancée : réserves, dés gardés et écartés, explosions, relances, succès, lancers vérifiables et rejouables. |
| [`tilttek/dice-probability`](https://github.com/TiltTek/dice-probability) | Les probabilités exactes d'une expression de dés, en fractions : distributions, au moins et au plus, moyenne, comparaisons. |
| [`tilttek/loot-tables`](https://github.com/TiltTek/loot-tables) | Des tables de butin lancées avec n'importe quel dé, tirées d'une façon que chacun peut vérifier, dés physiques compris. |

### Ludification

| Paquet | Description |
|---|---|
| [`tilttek/activity`](https://github.com/TiltTek/activity) | Un journal de ce que font les membres, chaque geste noté une seule fois, avec les projections que lisent tous les autres modules. |
| [`tilttek/activity-bundle`](https://github.com/TiltTek/activity-bundle) | Le journal d'activité dans une application Symfony : tables Doctrine DBAL, projections tenues à jour en direct ou par Messenger. |
| [`tilttek/points`](https://github.com/TiltTek/points) | Des points tenus dans un grand livre en partie double : devises, règles de gain, plafonds, expiration, dépenses et transferts. |
| [`tilttek/points-bundle`](https://github.com/TiltTek/points-bundle) | Des points dans une application Symfony : règles dans la configuration, grand livre dans Doctrine DBAL, soldes et historiques. |
| [`tilttek/progression`](https://github.com/TiltTek/progression) | De l'expérience, des niveaux sur une courbe, des saisons, des paliers et des séries comptées dans le fuseau horaire du membre. |
| [`tilttek/progression-bundle`](https://github.com/TiltTek/progression-bundle) | Niveaux et séries dans une application Symfony : pistes dans la configuration, barre de progression et série dans Twig. |
| [`tilttek/badges`](https://github.com/TiltTek/badges) | Des badges gagnés par l'activité des membres : critères, paliers, progression, badges cachés et vitrines que le membre choisit. |
| [`tilttek/badges-bundle`](https://github.com/TiltTek/badges-bundle) | Des badges dans une application Symfony : critères dans la configuration, attributions enregistrées, vitrine dans Twig, attestations Open Badges. |
| [`tilttek/quests`](https://github.com/TiltTek/quests) | Quêtes et défis : étapes lues dans l'activité, dates qui reviennent, enchaînements, quêtes d'équipe et duels. |
| [`tilttek/quests-bundle`](https://github.com/TiltTek/quests-bundle) | Des quêtes dans une application Symfony : dans la configuration ou la base de données, avec leur progression, leurs événements et leurs gabarits. |
| [`tilttek/collections`](https://github.com/TiltTek/collections) | Objets à collectionner et séries : exemplaires et doublons, l'objet du jour, paquets tirés selon des chances publiées. |

Chaque paquet est en `0.x` : son API peut encore changer d'une version mineure à l'autre, comme le note son CHANGELOG.

## Installer un paquet

Demandez le paquet voulu ; un bundle amène sa bibliothèque avec lui :

```sh
composer require tilttek/points-bundle
```

Le README et le dossier `doc/` de chaque paquet disent ce qu'il exige, comment le configurer et ce qu'il laisse à
l'application.

## Organisation

- `packages/<nom>/` : un paquet par dossier, installable seul, avec son `composer.json`, son README en anglais et en
  français, son CHANGELOG, sa licence, sa documentation dans `doc/` (miroir français dans `doc/fr/`) et ses tests.
- La racine les relie par un dépôt Composer `path` et porte l'outillage de qualité commun, qui vient de
  [`phil/dev-tools`](https://github.com/parler-haut-interagir-librement/dev-tools).

## Contribuer

Les tickets et les demandes de fusion vont dans **ce dépôt**, jamais dans les dépôts copiés, que chaque copie écrase.
Lisez le [guide de contribution](CONTRIBUTING.md) et le [code de conduite](CODE_OF_CONDUCT.md). Signalez une
vulnérabilité en privé, comme l'explique la [politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE). Chaque
paquet porte son propre fichier de licence.
