<!-- phil-dev source-sha256=582a4f2548a15a77ce65fdf51d867dee0c1a2c7edfd292ccd65e9bb02184267e -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\EncounterException` : attrapez-la pour les attraper toutes.

| Exception | Étend | Quand |
|---|---|---|
| `Exception\InvalidEncounter` | `InvalidArgumentException` | Une valeur hors de sa forme ou de ses bornes : un identifiant, une cote, un titre, une quantité, un bonus, trop de participants ou de combattants, un combattant en double, les deux côtés d'un placement, des règles de difficulté hors de leurs bornes ou de leur ordre, une cote que les règles ne connaissent pas, des données que le lecteur de déclaration refuse. |
| `Exception\EncounterRefused` | `DomainException` | Un appel non permis sur la rencontre dans son état : par quelqu'un d'autre que son meneur de jeu, sur une rencontre lancée, sur un participant qu'elle ne contient pas, un déplacement à côté de lui-même, un lancement sans participant, une rencontre que les règles ne peuvent pas évaluer. |
| `Exception\EncounterNotFound` | `RuntimeException` | Aucune rencontre de cet identifiant n'est gardée. |
| `Exception\EncounterConflict` | `RuntimeException` | La rencontre a changé depuis sa lecture, ou une de cet identifiant existe déjà : rien n'est gardé, et le changement est refait sur ce qu'on lit de nouveau. |
| `Exception\UnknownSystem` | `RuntimeException` | Aucune règle de difficulté n'est déclarée pour le système de jeu, ou aucun livre de règles n'est donné pour évaluer. |
