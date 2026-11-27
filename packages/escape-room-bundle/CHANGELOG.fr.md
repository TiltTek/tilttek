<!-- phil-dev source-sha256=2a984aefc02f31a44c63c9bb1e5cab3b7d70792c180f61eeac946fdc9b9bf489 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/escape-room-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.0-alpha - 2026-11-25

L'effacement d'un membre supprime ce qui ne sert plus et ne pseudonymise que ce dont une partie en cours ou une
réservation a besoin ; la raison de chaque choix est dans [Vie privée](doc/fr/privacy.md#erasure). Un `GameStore` ou un
`ScreenStore` à vous implémente `forget()`, et un `ResultStore` à vous implémente `forgetTeam()` à la place
d'`eraseTeam()`.

### Ajouté

- `GameStore::forget()` et `ScreenStore::forget()`, qui suppriment les parties et les écrans nommés, sur Doctrine DBAL
  par pages de 500 identifiants et en mémoire.
- `ResultStore::forgetTeam()`, qui supprime les résultats d'une équipe.

### Modifié

- `EscapeRoomPrivacy::erase()` supprime les parties du membre qui sont finies, leurs écrans, et chaque résultat du
  membre ; une partie encore en cours continue sous le pseudonyme, avec le consentement à montrer son résultat retiré ;
  un écran que le membre a montré comme animateur est supprimé une fois sa partie finie ou disparue, et garde le
  pseudonyme tant qu'elle se déroule ; les réservations restent sous le pseudonyme. Le rapport nomme aussi
  `tilttek_escape_room_screen.game`, et chaque colonne comme `deleted`, ou `pseudonymised` tant qu'une partie se déroule
  et pour les réservations.
- `GameStore::eraseTeam()` retire le consentement à montrer le résultat de chaque partie qu'il réécrit.

- Les prérequis de développement acceptent `phil/privacy-bundle` 0.3, la version publiée le 2026-11-24, et aucune
  version antérieure, pour que le paquet s'installe seul à côté de la version publiée de ce bundle.

### Retiré

- `ResultStore::eraseTeam()` : un résultat retiré ne sert à rien, et `forgetTeam()` le supprime.

### Sécurité

- `twig/twig` 3.31.1 ou plus récent requis, pour les correctifs de sécurité de cette version : son bac à sable vérifie
  la politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Les prérequis de développement acceptent `phil/privacy-bundle` 0.2, sa dernière version mineure publiée, et aucune
  version antérieure, pour que le paquet s'installe seul à côté de la version publiée de ce bundle.
- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, `symfony/ux-twig-component` 3.8, `symfony/service-contracts` 3.8.

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `TilttekEscapeRoomBundle` et la configuration `tilttek_escape_room` : le stockage, la connexion, les salles ou le
  catalogue de l'application, l'accès, les noms des équipes, les réservations et les règles des salles, la limite des
  propositions, le planificateur, Mercure, la mise en page, la taille du classement, le kit, la célébration, le domaine
  de traduction et le type d'un membre.
- `Application\GameDesk` : une partie lancée sous un identifiant que choisit l'application ; chaque coup lu dans le
  stockage, joué, réécrit avec le moment où son compte à rebours atteint zéro, et annoncé aux écrans ; le résultat d'une
  évasion enregistré ; l'écran des indices gardé ; les expirations une seule fois chacune, la plus ancienne en premier,
  par lots de 1 à 1 000 ; le consentement ; le classement public.
- `Application\Contract\RoomCatalog`, `EscapeRoomAccess`, `TeamNames`, `GameStore`, `ResultStore`, `ScreenStore`,
  `PartyBookings` et `ScreenPublisher`, avec `ConfiguredRoomCatalog`, `NoEscapeRoomAccess` et `UnnamedTeams` ;
  `Application\Screen`, `ScreenKind`, `StoredGame`, `EscapeRoomTopics` et `Exception\DeskRefused`.
- `EscapeRoomTables` et `EscapeRoomSchemaListener` : les cinq tables des parties, des résultats, des écrans, des
  réservations et des verrous des séances, ajoutées au schéma que l'ORM génère ; `DbalGameStore`, `DbalResultStore`,
  `DbalScreenStore` et `DbalSeatLedger`, une réservation décidée et écrite sous le verrou de sa séance.
- `InMemoryGameStore`, `InMemoryResultStore`, `InMemoryScreenStore` et `InMemorySeatLedger`, étiquetés `kernel.reset`.
- Les réservations des séances sur `phil/reservation`, branchées quand il est installé, dans la base de données ou en
  mémoire ; celles de l'application sinon.
- `EscapeRoomSchedule`, `ExpireDue` et `ExpireDueHandler` pour Symfony Scheduler ; la commande
  `tilttek:escape-room:expire`.
- `MercureScreenPublisher`, `ScreenSubscriptions` et `MercureHubPass` : chaque coup annoncé par une mise à jour privée
  qui ne nomme que le coup, le hub nommé ou détecté, le témoin d'autorisation du sujet de la partie.
- Les routes de la console, de l'écran des indices et de son état, du consentement et du classement ;
  `ConsoleController`, `ScreenController`, `ConsentController` et `LeaderboardController`, avec `Screens` et `Views`.
- Les gabarits de la console, de l'écran des indices, de son état et du classement, faits des recettes `Card`, `Badge`,
  `Button`, `Alert` et `Celebration` du kit PHIL UX ; les fonctions Twig `tilttek_escape_room_kit`,
  `tilttek_escape_room_domain` et `tilttek_escape_room_clock` ; les textes en anglais et en français.
- Les contrôleurs Stimulus `screen` et `console` de `@tilttek/escape-room`, avec les événements DOM
  `tilttek-escape-room:moved` et `tilttek-escape-room:finished`.
- `EscapeRoomPrivacy` pour `phil/privacy-bundle` : les parties, les résultats, les écrans et les réservations d'un
  membre exportés, et pseudonymisés sous une seule référence `erased:` de l'effacement, leurs résultats retirés du
  classement.
- Une recette Flex dans `recipe/0.1`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
