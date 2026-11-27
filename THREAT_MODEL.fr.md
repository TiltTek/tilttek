<!-- phil-dev source-sha256=8ca90a2d3739fffc37dc261ff90dc3fb0b047f1cd3ceb31943fe988a96d07e27 -->
# Modèle de menace

[English](THREAT_MODEL.md)

## Ce que fait ce projet et où entrent les données non fiables

Tilttek est un monodépôt de bibliothèques PHP 8.5 et de bundles Symfony pour les jeux et la ludification
(`packages/<name>/`, chacun publié sous `tilttek/<name>`) : dés, probabilités, générateurs aléatoires, tables de butin,
points, badges, quêtes, classements. Les bibliothèques sont en PHP pur ; chaque bundle branche sa bibliothèque dans une
application Symfony. Plusieurs d'entre elles évaluent de petits langages que des joueurs tapent dans un clavardage, un
formulaire ou une table virtuelle : ces données ne sont pas fiables, et un attaquant en contrôle chaque octet.

Les paquets qui reçoivent des données non fiables, et ce que chacun promet :

| Paquet | Données non fiables | Promesse |
|---|---|---|
| `dice` | Expressions de dés (notation, instructions, macros, dialectes) | L'analyse et le lancer restent dans les `Limits` (par défaut : longueur de l'entrée 256, dés 100, faces 1000, explosions 100, relances 100, répétitions 100, instructions 10, texte 4096 caractères) : au-delà d'une limite, l'expression est refusée avec `LimitExceeded`, jamais tronquée, et ne s'exécute jamais sans borne. Un dépassement d'entier est refusé, pas bouclé. Les lancers engagés par un germe ne peuvent être ni prédits avant la révélation ni changés après. |
| `dice-probability` | Expressions de dés | Le calcul exact est borné en travail et en mémoire ; au-delà de sa borne, il passe à une estimation avec germe ou refuse, sans jamais s'exécuter sans borne. |
| `generator` | Demandes de tirage (nom du générateur, nombre, germe, état renvoyé par l'appelant) ; listes et gabarits de générateurs quand une application laisse ses utilisateurs les écrire | Les gabarits s'exécutent dans un bac à sable Twig : aucun code PHP, aucun fichier, aucun réseau, seulement les balises, filtres, fonctions et méthodes permis. Chaque tirage reste dans les `Limits` (profondeur, choix, étapes des boucles et des affectations, mémoire, temps par résultat et par tirage, résultats, cellules, et les octets que produisent `replace` et `join`), vérifiées à l'intérieur de chaque rendu. Un état renvoyé par un appelant ne peut pas faire sortir un tirage de ces limites. |
| `loot-tables` | Demandes de tirage, dés entrés à la main, codes de boutique | Un tirage engagé ne peut pas être changé une fois engagé, ni son issue connue avant la révélation. |
| `points`, `badges`, `quests`, `leaderboards`, `progression`, `collections`, `exchange` | Actions signalées par les utilisateurs | Un utilisateur ne peut pas s'attribuer des points, des badges ou une progression hors des règles que l'application a configurées, ni agir sur les dossiers d'un autre utilisateur. |

Les paquets `*-bundle` portent les mêmes promesses à la frontière de l'application : champs et contraintes de
formulaire, filtres Twig, et l'API de tirage à débit limité de `generator-bundle` (désactivée par défaut).

Les paquets `phil/*` que ces paquets requièrent sont analysés dans leur propre monodépôt ; un défaut là-bas est hors de
portée ici, à moins qu'un paquet Tilttek leur passe des données qu'ils ne promettent pas de traiter.

## Les composants qui comptent le plus / le moins

- Le plus : `dice`, `dice-probability`, `generator`, puis `loot-tables` et les bundles qui les exposent.
- Dans la portée, mais moins exposés : les paquets de ludification, dont les données sont surtout les propres événements
  de l'application.
- Hors de portée : les ressources côté client de `accessible-dice` au-delà du HTML qu'elles émettent ; l'outillage de
  développement (`vendor-bin/`, `bin/`, `phil-dev.php`) ; les jeux de données d'essai et les applications de test sous
  `packages/*/tests/` ; les tables aléatoires livrées comme données dans `packages/generator/tables/`.

## Hypothèses

- Les paquets s'exécutent dans une application Symfony. Sa configuration (`config/`, options des bundles, limites, dés
  personnalisés, macros du site, catalogues de générateurs écrits par ses développeurs) est fiable.
- L'application authentifie ses utilisateurs et décide qui peut écrire des macros ou des générateurs ; les bibliothèques
  appliquent quand même leurs limites et leur bac à sable à tout ce qu'elles reçoivent.
- L'environnement d'exécution PHP, ses extensions et le système d'exploitation sont à jour ; leurs propres défauts sont
  hors de portée.

## Comment l'éprouver

- `composer test` exécute toutes les suites ; `vendor/bin/phpunit packages/<name>/tests` en exécute un paquet.
- `packages/*/tests/Fuzz/` contient les cibles de fuzzing du fuzzer installé dans `vendor-bin/fuzzer`.
- Chaque paquet documente ses limites dans `packages/<name>/doc/` (`dice/doc/limits.md`, `generator/doc/drawing.md`).

## Comment nous évaluons la gravité

- Critique : exécution de code ou accès aux fichiers ou au réseau depuis un gabarit de générateur ou une expression de
  dés ; sortie du bac à sable Twig.
- Élevée : prédire ou changer un lancer ou un tirage engagé ; agir sur les points, les badges ou la progression d'un
  autre utilisateur ; XSS stockée par un filtre Twig ou un champ de formulaire d'un bundle.
- Moyenne : déni de service par une seule expression ou requête courte : temps ou mémoire bien au-delà des limites
  documentées.
- Faible : fuites d'information dans les messages d'erreur ; défauts qui demandent une configuration fiable pour être
  hostiles.

## Ce qu'il faut laisser de côté

- La qualité statistique des tirages non cryptographiques avec germe, qui sont faits pour être rejoués.
- Le comportement que seule une configuration fiable peut déclencher, comme le disent les hypothèses.

## Contact de sécurité

Voyez [`SECURITY.fr.md`](SECURITY.fr.md) à la racine du monodépôt.
