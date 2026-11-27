<!-- phil-dev source-sha256=0ccee190d9c219984b82e54e39785ce9dd38aaab740437263b23214dcbe55f03 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/table-safety-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Modifié

- Le rapport de `TableSafetyPrivacy` désigne `tilttek_table_safety_signal` comme caviardée plutôt que pseudonymisée :
  l'effacement vide l'auteur d'un signal nommé et n'écrit aucun pseudonyme, et le signal reste jusqu'à la purge, sa
  table et son moment pouvant désigner le participant. La raison de chaque choix est dans
  [Vie privée](doc/fr/privacy.md).
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
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, `symfony/ux-twig-component` 3.8, `symfony/translation-contracts`
  3.8.

## 0.1.0-alpha - 2026-11-20

### Ajouté

- La configuration : le stockage, la connexion, l'accès, la mise en page, les valeurs par défaut d'une table, le hub
  Mercure, la conservation des signaux, le seuil des étoiles et des souhaits, le sous-dossier du kit et le type de la
  référence d'un participant.
- `TableAccess` et `NoTableAccess` : qui participe à une table et qui la mène, selon l'application.
- Les stockages sur Doctrine DBAL et les quatre tables, ajoutées au schéma que l'ORM génère par
  `TableSafetySchemaListener` ; les participations gardent le moment de la première réponse, `first_answered_at`, dont
  `doc/upgrading.md` décrit la migration.
- Les formulaires `ThemeAnswersType`, `SessionZeroType`, `StarsAndWishesType`, `SafetyToolsType` et `TableSettingsType`.
- Les écrans de la table, des réponses, de la séance zéro, des étoiles et des souhaits et du meneur de jeu, faits des
  recettes `Card`, `Badge`, `Button` et `Alert` du kit, qui fonctionnent sans JavaScript, en anglais et en français. Ce
  que la table a convenu est la liste protectrice pour le meneur de jeu, dès la première réponse et sans auteurs, et le
  résumé par groupes entiers du seuil des réponses pour les joueurs ; la séance zéro envoie un besoin de sécurité vers
  les limites et les voiles et vers la liste de consentement.
- Les routes des signaux : la levée, le fil et le marquage comme vu.
- `MercureSignalObserver` et `SignalSubscriptions` : les signaux poussés vers les sujets privés de leur table, avec des
  identifiants seulement ; un hub qui échoue, par URL ou intégré au serveur, est journalisé.
- Le contrôleur Stimulus `tilttek--table-safety--signals`, qui envoie les signaux en arrière-plan et annonce chaque
  nouveau signal.
- Les cartes imprimables d'une table — la carte X, la fiche des limites et des voiles et celle de la liste de
  consentement —, vierges, sous forme de page sans JavaScript et, avec `phil/paged-media`, en PDF par `SafetyCardsPdf`.
- La commande `tilttek:table-safety:purge-signals`.
- `TableSafetyPrivacy` : l'exportation et l'effacement d'un participant pour `phil/privacy-bundle`.
- Les fonctions Twig `table_safety_kit()` et `table_safety_tools()`.
- La recette Symfony Flex, dans `recipe/`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
