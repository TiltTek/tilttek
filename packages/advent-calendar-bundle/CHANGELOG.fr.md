<!-- phil-dev source-sha256=bfaaaa89dccca6b7641155835935b924c15ee4a9614299d845064adb49428a4a -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/advent-calendar-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Modifié

- Les prérequis de développement acceptent `phil/privacy-bundle` 0.3 et `phil/ux-kit` 0.6, les versions publiées le
  2026-11-24, et aucune version antérieure, pour que le paquet s'installe seul à côté des versions publiées.

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

- La configuration : le stockage, la connexion, le gestionnaire d'entités, le résolveur de membre, la mise en page, le
  sous-dossier du kit, la célébration, les calendriers et le type de la référence d'un membre pour
  `phil/privacy-bundle`.
- `MemberResolver`, `NoMember` et `Members` : le membre courant sous forme de référence stable, jamais d'adresse
  courriel.
- `CalendarCatalogue`, `ConfiguredCalendars` et `Calendars` : les calendriers de la configuration, vérifiés à la
  construction du conteneur, avant ceux de la base de données.
- `CalendarRecord`, `DoctrineCalendarCatalogue` et les formulaires `CalendarType` et `SurpriseType` : les calendriers
  qu'un site modifie par l'ORM, chacun vérifié par la bibliothèque quand il est soumis et quand il est lu.
- `DbalOpeningStore` et sa table, ajoutée au schéma que l'ORM génère par `OpeningsSchemaListener`, avec une clé primaire
  qui fait qu'une ouverture n'arrive qu'une fois et une colonne `delivered` qui garde dû le `BoxOpened` d'une ouverture
  jusqu'à ce qu'il ait été distribué sans exception ; `InMemoryOpenings` pour les tests. Le bundle requiert
  `tilttek/advent-calendar` `^0.2@dev`, dont l'`OpeningStore` garde la remise de chaque ouverture.
- L'écran d'un calendrier et l'ouverture d'une case, faits des recettes `Card`, `Badge`, `Button` et `Alert` du kit, qui
  fonctionnent sans JavaScript, en anglais et en français ; une ouverture refusée répond 409 avec sa raison.
- `Celebrations` et `CelebrationListener` : la première ouverture qu'annonce `BoxOpened`, célébrée par la `Celebration`
  du kit.
- Le contrôleur Stimulus `tilttek--advent-calendar--boxes`, qui ouvre une case en arrière-plan, y déplace le focus et
  l'annonce.
- Les commandes `tilttek:advent-calendar:list`, `tilttek:advent-calendar:show` et `tilttek:advent-calendar:check`.
- `AdventCalendarPrivacy` : l'exportation et l'effacement d'un membre pour `phil/privacy-bundle`.
- La fonction Twig `advent_calendar_kit()`.
- La recette Symfony Flex, dans `recipe/`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
