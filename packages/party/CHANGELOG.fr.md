<!-- phil-dev source-sha256=ca4cf0f31baca4e0736411ed8afdb4668d38d3b0db2b54d1862a15469c6d763b -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/party` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Party` : un groupe sous la référence de l'application, pour une campagne, avec un nom de 1 à 100 caractères et la clé
  de son système de jeu, ou aucune.
- `Member` et `Standing` : les membres d'un groupe sous leurs propres références, chacun tenant de 0 à 5 rôles distincts
  par clé — aucun par défaut, pour un jeu sans classes ni métiers —, actif ou absent — un membre absent garde sa place
  et ne compte nulle part.
- `Parties` : former, renommer, changer le système et dissoudre un groupe ; rejoindre, quitter — le butin porté retourne
  à la réserve —, attribuer des rôles et fixer le statut ; lire un groupe, ses membres et les groupes d'un membre ;
  effacer un membre de chaque groupe, ses notes gardées sans leur auteur. L'application décide qui peut agir et passe la
  référence de celui qui agit, que portent les événements.
- `Purse` : les pièces partagées par monnaie, des nombres entiers de 1 à 1 000 000 000 000 sous au plus 20 clés de
  monnaie, jamais négatifs ; dépôts et retraits.
- `Split` : les parts d'une monnaie entre les membres actifs ou les membres choisis, en pièces entières, le reste gardé
  dans la bourse ; `shares()` les lit, `distribute()` les retire.
- `LootItem` : un butin partagé sous des clés, une quantité de 1 à 1 000 000, porté par un membre ou dans la réserve ;
  ajouter, donner, mettre en réserve et retirer.
- `Note` : des notes que les membres partagent sous des clés, de 1 à 5000 caractères, avec leur auteur et leurs moments
  d'écriture et de modification.
- `Composition\Role`, `Composition\RoleCatalogue`, `Composition\CompositionAdvisor`, `Composition\Composition`,
  `Composition\RoleCoverage` et `Composition\Coverage` : la composition d'un groupe par rapport aux rôles de groupe
  qu'un système de `tilttek/rpg-systems` déclare, ou à des rôles donnés à la main — rôles manquants, couverts et
  encombrés par rapport à une part équitable, membres sans rôle et rôles inconnus, comme conseil et jamais comme refus.
  Les rôles sont facultatifs : un catalogue tient de 0 à 50 rôles, `RoleCatalogue::none()` et un système sans rôles de
  groupe en donnent un vide, et une composition lue par rapport à lui ne donne aucun conseil (`hasAdvice()` faux).
- `Limits` : les membres par groupe, les groupes par membre, les entrées de butin et les notes par groupe, par défaut
  12, 10, 500 et 200.
- `Contract\PartyStore`, avec `Store\InMemoryPartyStore`.
- `Contract\PartyObserver`, averti par `PartyChanged` avec son `Change`, identifiants seulement.
- Des exceptions sous une seule interface `PartyException` : `PartyRefused` avec son `Refusal`, `UnknownParty` et
  `InvalidParty`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
