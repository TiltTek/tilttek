<!-- phil-dev source-sha256=26804e76fcc2367aac0acd18e179d84ddda6c79ce9c2569a43aae81f39f427cf -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/generator-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.4-alpha - 2026-11-25

### Sécurité

- `twig/twig` 3.31.1 ou plus récent requis, pour les correctifs de son bac à sable dans 3.31.1 ; les gabarits des
  générateurs produisent leur rendu dans un `Twig\Sandbox\Sandbox` de `tilttek/generator`.

## 0.2.3-alpha - 2026-11-24

### Modifié

- La documentation en français : les lignes de sa page sur l'API tiennent en 120 colonnes.
- `tilttek/dice` 0.3 requis : 0.2 n'est plus accepté.

### Sécurité

- `twig/twig` 3.31 ou plus récent requis, pour les correctifs de son bac à sable dans 3.29 ; les gabarits des
  générateurs produisent leur rendu dans un `Twig\Sandbox\Sandbox` de `tilttek/generator`.

## 0.2.2-alpha - 2026-11-20

### Modifié

- `tilttek/dice` 0.2 ou 0.3 requis : 0.1 n'est plus accepté.

## 0.2.1-alpha - 2026-11-17

### Modifié

- `tilttek/dice` 0.3 accepté, tout comme 0.1 et 0.2.

## 0.2.0-alpha - 2026-11-14

### Ajouté

- Les limites `steps`, `bytes` et `megabytes` de `tilttek/generator` dans `tilttek_generator.limits`.
- Les attributions des résultats dans le gabarit `@TilttekGenerator/draw.html.twig`, dans un paragraphe de classe
  `tilttek-generator-attribution`, et dans la cible `attribution` du contrôleur Stimulus, rafraîchie à chaque tirage ;
  l'API les donne sous `attributions`.
- Un test qui vérifie que chaque gabarit des générateurs est chargé depuis un fichier compilé du dossier du cache,
  jamais par `eval()`.

### Modifié

- `tilttek_generator.cache` est le dossier des gabarits compilés de Twig, `%kernel.cache_dir%/tilttek_generator/twig`
  par défaut, `true` désignant la valeur par défaut ; `false` et un texte vide sont refusés, pour que les gabarits ne
  soient jamais chargés par `eval()`.
- `tilttek/generator: ^0.2@dev`, pour ses nouvelles limites et attributions.
- `tilttek/dice` 0.2 accepté, tout comme 0.1.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `CachedCatalog` : les listes et les générateurs des fichiers et des dossiers, ou de `config/generators` du projet,
  gardés dans un fichier PHP du cache et réécrits en mode débogage quand un fichier change, même dans la seconde où le
  cache a été écrit, `FileChanges` interrogeant les fichiers chaque fois ; `GeneratorFactory`, qui construit le
  `Generator` public avec les limites, la langue et les grammaires configurées, et les dés de `tilttek/dice-bundle`
  quand il est installé.
- La configuration `tilttek_generator` : chemins, langue par défaut, mots à h aspiré de la grammaire française, mots
  avec un a tonique de la grammaire espagnole, limites, cache de Twig, tables aléatoires de la bibliothèque (`tables`)
  et leur langue (`tables_locale`, anglais ou français), stockage des états, son pool et son verrou (`state`), et l'API
  de tirage avec ses générateurs publics, ses clients, ses jetons et ses limiteurs de débit ; les tirages publics
  limités par adresse par défaut, soixante par minute (`api.public_limit`), sur le constructeur de limiteurs de débit de
  `symfony/rate-limiter`, et le conteneur refusé par `PublicRateLimiterPass` quand ce constructeur manque.
- La fonction Twig `tilttek_draw`, le gabarit `@TilttekGenerator/draw.html.twig`, et le contrôleur Stimulus qui tire de
  nouveau sur place, en écrivant les résultats comme du texte, avec son état et `aria-busy`, mappé pour AssetMapper.
- Les commandes `tilttek:generator:validate`, qui énumère chaque problème, `tilttek:generator:create`, qui écrit un
  générateur de texte, structuré, de carte ou avec mémoire, ou copie une table aléatoire de la bibliothèque sous un
  nouveau nom (`--from`), en anglais ou en français (`--locale`), et `tilttek:generator:draw`, qui tire depuis l'état
  gardé sous `--key`, oublié d'abord par `--reset`, et écrit l'état qu'il laisse.
- Les tables avec mémoire : `MemoryTables`, public, qui tire depuis l'état gardé par générateur et par clé et garde le
  nouveau, et lit, fixe et oublie un état, en refusant une clé vide ou de plus de 200 caractères (`InvalidStateKey`) ;
  le port `StateStore`, public, sur un pool de cache PSR-6 sans expiration sous des clés hachées (`CacheStateStore`,
  clés hachées par SHA-256), en mémoire réinitialisée avec le noyau (`InMemoryStateStore`), ou le propre service de
  l'application ; le port `StateLock`, désactivé par défaut, sur la fabrique de verrous de Symfony que nomme
  `state.lock` (`SymfonyStateLock`, un verrou par une empreinte SHA-256 du générateur et de la clé, attendu et gardé 30
  secondes au plus), qui tient un tirage du chargement de son état à l'enregistrement du nouveau, ainsi qu'une fixation
  ou un oubli, pour que deux tirages sur le même état ne perdent pas une mise à jour ; `symfony/lock` suggéré.
- L'API de tirage : `ApiAccess`, `Client` et `Grant`, désactivés par défaut, les générateurs publics comptés par adresse
  et les clients par un jeton comparé en temps constant ; `DrawRequest`, qui lit l'état qu'une table avec mémoire a
  renvoyé (`state[name]=value`) et répond avec le nouveau, et refuse avec un 400 un nom, un germe ou un état qui n'est
  pas en UTF-8 ; `DrawController` et sa route, qui répond en JSON avec `no-store`, et en détails de problème pour 400,
  401, 403, 404, 422 et 429 avec `Retry-After`, la cause d'un tirage échoué dite à un client et non au public ; le port
  `DrawRateLimiter` sur les limiteurs de débit de Symfony.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
