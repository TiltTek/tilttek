<!-- phil-dev source-sha256=b9f7cdbebed0684c67308c733e185a439893ba876a8b363dd64a034d1751a016 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/handouts` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Handout` : une aide de jeu d'une table sous la référence de l'application, avec un titre de 1 à 150 caractères et un
  texte de 1 à 20 000 caractères, des pièces jointes, ou les deux, et une révision que chaque modification augmente.
- `Attachment` : un fichier du stockage de l'application nommé par sa référence, avec son type de média et une
  description de 1 à 500 caractères ; au plus 50 fichiers distincts par aide de jeu.
- `MediaTypes` : les types de média qu'une pièce jointe peut avoir — par défaut les images PNG, JPEG, WebP, AVIF et GIF,
  le PDF, les sons MP3 et Ogg, les vidéos MP4 et WebM et le texte brut, SVG laissé de côté —, de 1 à 100 choisis par
  l'application.
- `Handouts` : créer, modifier et retirer une aide de jeu et lire les aides de jeu d'une table ; révéler à une
  `Audience` — tout le monde à la table ou de 1 à 500 joueurs — et cacher de nouveau ; `canSee()` et le public sous
  forme d'entrées `Disclosure` ; préparer une révélation sur un `Trigger`, à un moment ou sur un signal, sous forme
  d'entrées `StagedReveal`, les retirer, `releaseDue()` pour le planificateur de l'application et `cue()` pendant la
  partie ; effacer un joueur de chaque table. L'application décide qui peut agir et passe la référence de la personne
  qui agit, que portent les événements.
- `Sighting`, `Visible` et `Freshness` : ce que chaque joueur a ouvert, à quelle révision et quand ; les aides de jeu
  qu'un joueur voit, la plus anciennement révélée en premier, non vues, périmées ou vues ; les aides de jeu qu'il n'a
  pas ouvertes à leur révision actuelle.
- `Limits` : les aides de jeu par table, les pièces jointes par aide de jeu, les publics par aide de jeu et les
  révélations par libération ou par signal, par défaut 200, 10, 50 et 100.
- `Contract\HandoutStore`, avec `Store\InMemoryHandoutStore`.
- `Contract\HandoutObserver`, averti par `HandoutChanged` avec son `Change`, avec des identifiants seulement.
- Des exceptions sous une seule interface `HandoutException` : `HandoutRefused` avec son `Refusal`, `UnknownHandout` et
  `InvalidHandout`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
