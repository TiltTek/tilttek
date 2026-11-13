# Tilttek

**Des bibliothèques PHP et des bundles Symfony ouverts pour les jeux et la ludification : dés, butin, générateurs
aléatoires, météo, points, niveaux, badges, quêtes, collections et échanges.**

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
| [`tilttek/dice-bundle`](https://github.com/TiltTek/dice-bundle) | Des dés dans une application Symfony : limites et dés personnalisés dans la configuration, lancers rejouables dans Doctrine, macros, champ de formulaire, Twig et détail accessible d'un lancer. |
| [`tilttek/dice-probability`](https://github.com/TiltTek/dice-probability) | Les probabilités exactes d'une expression de dés, en fractions : distributions, au moins et au plus, moyenne, comparaisons. |
| [`tilttek/loot-tables`](https://github.com/TiltTek/loot-tables) | Des tables de butin lancées avec n'importe quel dé, tirées d'une façon que chacun peut vérifier, dés physiques compris. |
| [`tilttek/accessible-dice`](https://github.com/TiltTek/accessible-dice) | Un formulaire de dés accessible pour Symfony, avec ou sans JavaScript : grandes cibles, faces dites par libellé, forme et valeur, résultats annoncés aux lecteurs d'écran. |
| [`tilttek/secret-rolls`](https://github.com/TiltTek/secret-rolls) | Les jets cachés du meneur, engagés avant d'être faits et vérifiables par les joueurs après leur dévoilement. |

### Générateurs

| Paquet | Description |
|---|---|
| [`tilttek/generator`](https://github.com/TiltTek/generator) | Des générateurs aléatoires écrits comme des données : listes pondérées, noms de Markov, gabarits Twig dans un bac à sable, grammaire en six langues, cartes, tables à mémoire, tirages rejouables. |
| [`tilttek/generator-bundle`](https://github.com/TiltTek/generator-bundle) | Les générateurs aléatoires dans une application Symfony : listes en YAML et JSON, tables aléatoires prêtes à tirer, commandes, Twig, contrôleur Stimulus et API de tirage sous limite de débit. |
| [`tilttek/weather`](https://github.com/TiltTek/weather) | La météo du monde de jeu : saisons et zones climatiques, conditions qui suivent la veille, températures tirées dans la plage de la saison, reproductible à partir d'une graine. |
| [`tilttek/heraldry`](https://github.com/TiltTek/heraldry) | Des armoiries tirées d'une graine, blasonnées en anglais et en français, et écrites en SVG sûr. |

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
| [`tilttek/collections-bundle`](https://github.com/TiltTek/collections-bundle) | Des collections dans une application Symfony : catalogue dans la configuration ou la base de données, exemplaires dans Doctrine DBAL, album et objet du jour dans Twig. |
| [`tilttek/exchange`](https://github.com/TiltTek/exchange) | Des récompenses achetées avec des points en deux temps, des points donnés entre membres et des objets échangés par offres et contre-offres en une seule transaction. |
| [`tilttek/leaderboards`](https://github.com/TiltTek/leaderboards) | Des classements tenus à jour à partir du journal d'activité : périodes et fenêtres glissantes, départages, instantanés, ligues hebdomadaires, titres et équipes. |
| [`tilttek/advent-calendar`](https://github.com/TiltTek/advent-calendar) | Un calendrier de l'Avent : une case par jour dans le fuseau du calendrier, une politique d'ouverture, chaque case ouverte une seule fois et annoncée par un événement. |
| [`tilttek/writing-goals`](https://github.com/TiltTek/writing-goals) | Les objectifs d'écriture d'un auteur : une cible en mots ou en pages, un plan sur une courbe, un quota du jour recalculé selon ce qui reste, aucune série comptée. |
| [`tilttek/seasonal-events`](https://github.com/TiltTek/seasonal-events) | Des saisons annuelles par règle RFC 5545, avec leurs contenus et les multiplicateurs qu'elles annoncent. |
| [`tilttek/sets`](https://github.com/TiltTek/sets) | Des ensembles versionnés à paliers et à récompenses : une brique neutre sur laquelle collections et badges se bâtissent. |
| [`tilttek/stickers`](https://github.com/TiltTek/stickers) | Des autocollants posés sur des surfaces, avec des limites par surface et par détenteur, et la modération. |
| [`tilttek/bingo`](https://github.com/TiltTek/bingo) | Un bingo aux cartes tirées d'un secret du serveur, aux cartes imprimées vérifiables, et aux victoires vérifiées sur le serveur. |
| [`tilttek/game-engine`](https://github.com/TiltTek/game-engine) | Des jeux au tour par tour en journal de coups : hasard semé et engagé d'avance, annulation, instantanés et rejeu. |
| [`tilttek/game-engine-bundle`](https://github.com/TiltTek/game-engine-bundle) | Des tables de jeu dans Symfony : identifiants de siège opaques, journal en DBAL, verrou et mises à jour Mercure privées. |
| [`tilttek/table-safety`](https://github.com/TiltTek/table-safety) | Des outils de sécurité pour les jeux de table : lignes et voiles, signaux anonymes, et le crédit de chaque outil. |

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
