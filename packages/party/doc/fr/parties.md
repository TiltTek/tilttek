<!-- phil-dev source-sha256=7f52053a0b47132cb9a709552fca7bf82fd717176958397a0c0d5760db5587fc -->
# Groupes et membres

## Former un groupe

Un groupe est formé pour une campagne, sous la référence que lui donne l'application, avec un nom et, facultativement,
la clé de son système de jeu :

```php
$parties->form($party, $campaign, 'The Lantern Bearers', 'srd-5.2', $by);
```

Une référence se forme une seule fois : un second `form()` sous elle est refusé avec `Refusal::AlreadyFormed`. Le nom
compte de 1 à 100 caractères UTF-8, sans caractères de contrôle ni espaces en début ou en fin ; la clé du système,
comme toute clé de la bibliothèque, compte de 1 à 96 lettres minuscules, chiffres, `_`, `-`, ou points isolés entre
eux.

`rename()` change le nom, `useSystem()` change le système ou l'efface avec `null` — les membres gardent leurs rôles,
que la composition lit alors d'après le nouveau système —, et `disband()` retire le groupe avec ses membres, sa bourse,
son butin et ses notes.

## Qui peut agir

La bibliothèque ne décide d'aucune permission. L'application vérifie qui peut agir — le maître de jeu, les membres, un
organisateur — avant d'appeler, et passe la référence de qui agit en dernier argument, `$by`, que portent les
événements. La seule règle que la bibliothèque garde est que l'auteur d'une note et le porteur d'un butin appartiennent
au groupe.

## Membres

Un personnage rejoint le groupe sous sa propre référence, actif, avec les clés des rôles qu'il remplit :

```php
$parties->join($party, $character, ['role.healer', 'role.scout']);
```

Un membre remplit de 0 à 5 rôles distincts, et rejoint sans aucun par défaut : les rôles sont facultatifs, pour un jeu
sans classes ni métiers comme pour un jeu qui en a. Une clé donnée deux fois est gardée une fois, dans l'ordre où elle a
été donnée en premier. Les rôles ne sont jamais vérifiés d'après un système : un rôle que le système ne déclare pas est
signalé par la composition, jamais refusé.

Un personnage rejoint un groupe une seule fois (`Refusal::AlreadyMember`) ; un groupe compte au plus
`Limits::$membersPerParty` membres (`Refusal::Full`), et un personnage appartient à au plus `Limits::$partiesPerMember`
groupes (`Refusal::TooManyParties`).

`assignRoles()` remplace les rôles d'un membre. `setStanding()` le met `Standing::Active` ou `Standing::Away` : un
membre en absence garde sa place, ses rôles et le butin qu'il porte, mais ne reçoit par défaut aucune part de la bourse
et ne compte nulle part dans la composition.

`members()` lit les membres, le plus ancien arrivé en premier, les égalités dans l'ordre des octets de leurs
références ; `member()` en lit un, ou null ; `partiesOf()` lit les groupes auxquels appartient un personnage, le plus
ancien rejoint en premier.

## Quitter

`leave()` retire un membre : le butin qu'il portait retourne à la réserve, les notes qu'il a écrites restent avec leur
auteur. Les actes sur une référence qui n'est pas un membre sont refusés avec `Refusal::NotMember`.

## Effacement

`forget($member)` efface un personnage de tous les groupes : il quitte chacun, le butin qu'il portait retourne à la
réserve, et les notes qu'il a écrites, dans n'importe quel groupe, perdent leur auteur et gardent leur texte. Chaque
groupe reçoit `Change::Forgotten`.
