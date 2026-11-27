<!-- phil-dev source-sha256=bf61dd5be4f9a55ed5edde6faa8408c72e022e01115fd48188fad14e062e9149 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/luck-tokens-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Ajouté

- `DbalTokenRules::removeGameMaster()`, qui retire un membre des meneurs de jeu de chaque table, par pages de 500
  tables.

### Modifié

- `LuckTokensPrivacy` prend les règles gardées dans la base de données comme quatrième argument facultatif, donné par le
  bundle avec `rules: doctrine` : l'effacement retire le membre des meneurs de jeu de chaque table, et le rapport nomme
  `tilttek_luck_tokens_rules.game_masters` comme supprimée. La raison de chaque choix est dans
  [Effacer un membre](doc/fr/erasing-a-member.md).
- `DbalTokenLedger::eraseHolder()` vide la raison des écritures que détient le membre, et le rapport de
  `LuckTokensPrivacy` nomme `tilttek_luck_tokens_entry.reason` comme supprimée ; le détenteur et l'acteur restent
  pseudonymisés.
- Les prérequis de développement acceptent `phil/privacy-bundle` 0.3, la version publiée le 2026-11-24, et aucune
  version antérieure, pour que le paquet s'installe seul à côté de la version publiée de ce bundle.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Les prérequis de développement acceptent `phil/privacy-bundle` 0.2, sa dernière version mineure publiée, et aucune
  version antérieure, pour que le paquet s'installe seul à côté de la version publiée de ce bundle.
- Les tests : un processus du test de concurrence attend plus longtemps après chaque conflit de suite avant de
  réessayer, jusqu'à 64 ms, pour qu'un auteur qui tient le verrou d'écriture de SQLite et qu'une machine occupée met en
  pause valide avant que les autres épuisent leurs tentatives.
- La documentation en français : les lignes du README et de la page sur les tests tiennent en 120 colonnes.
- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4.

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `TilttekLuckTokensBundle` et sa configuration `tilttek_luck_tokens` : le stockage des registres, Doctrine DBAL ou
  mémoire, et sa connexion ; les règles des tables tirées de la configuration, de la base de données ou d'un service de
  l'application, les tables configurées vérifiées à la compilation du conteneur ; l'horloge ; la taille d'un registre ;
  qui contrôle les jetons de qui — chaque détenteur les siens, les voters de la sécurité, ou un service — ; les
  verrous ; le type d'un membre pour le bundle de vie privée.
- `LuckTokens` enregistré avec les règles, le registre, le répartiteur d'événements de l'application, l'accès et
  l'horloge, pour que les événements atteignent les écouteurs de l'application.
- `Infrastructure\Doctrine\DbalTokenLedger` : les registres dans `tilttek_luck_tokens_entry`, la table et la position
  formant la clé primaire, les écritures d'une opération insérées en une seule transaction après une nouvelle lecture de
  la dernière position à cet endroit, un autre auteur à la même position, un interblocage ou un délai de verrou dépassé
  répondu par `LedgerConflict` ; chaque solde lu depuis l'index de la dernière écriture du détenteur pour ce type, les
  écritures d'une opération depuis un index ordonné par position ; un membre effacé sous un pseudonyme par deux mises à
  jour en une seule transaction.
- `Infrastructure\Doctrine\DbalTokenRules` : les règles de chaque table en JSON dans `tilttek_luck_tokens_rules`,
  enregistrées, retirées et relues en les vérifiant.
- `Infrastructure\Doctrine\LuckTokensTables` et `LuckTokensSchemaListener` : les tables ajoutées au schéma que l'ORM
  génère pour leur connexion, la table des règles seulement quand les règles y sont gardées ; sur MySQL et MariaDB, les
  textes comparés octet par octet.
- `Infrastructure\Rules\ConfiguredTokenRules` : les règles des tables de la configuration, lues une seule fois.
- `Application\LockedLuckTokens` et `Application\LockScope` : les opérations sous un verrou Symfony — de chaque
  détenteur, des deux détenteurs d'un transfert dans un même ordre, ou de la table entière ; la table pour une séance,
  le membre pour un effacement —, libéré même quand l'opération échoue.
- `Infrastructure\Security\SecurityHolderAccess`, `HolderControlVoter` et `HolderControl` : l'accès des acteurs aux
  détenteurs demandé aux voters de la sécurité, le voter du bundle accordant à chaque détenteur ses propres jetons et
  s'abstenant sinon.
- `Infrastructure\Privacy\LuckTokensPrivacy` : l'effacement d'un membre pour `phil/privacy-bundle`, sous son verrou
  quand les verrous sont activés, signalé comme pseudonymisé.
- Les tests des stockages sur SQLite, MySQL 8.4 et PostgreSQL 17, quatre processus qui accordent en même temps avec et
  sans les verrous, et un banc d'essai d'une dépense après 10 000 écritures dans la base de données.
- La recette Flex dans `recipe/0.1` : le bundle et `config/packages/tilttek_luck_tokens.yaml`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
