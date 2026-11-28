<!-- phil-dev source-sha256=e3c6c815d5e522ca6407976e7a6b5644b235c5184ea2423e46c700dfdb7ddcfa -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\InitiativeException` : interceptez-la pour les intercepter
toutes.

| Exception | Étend | Quand |
|---|---|---|
| `Exception\InvalidEncounter` | `InvalidArgumentException` | Une valeur sort de sa forme ou de ses bornes : un identifiant, les pioches d'un combattant, trop de combattants, un combattant en double, un combattant qui se joint avec une carte, une face que le classement ne connaît pas, un ordre de couleurs. |
| `Exception\InitiativeRefused` | `DomainException` | Un changement non permis : par quelqu'un d'autre que le maître de jeu ou le contrôleur, sur une rencontre terminée, le tour d'un combattant qui ne le détient pas, un combattant suivant qui n'attend pas, un identifiant pris, un jeu trop petit. |
| `Exception\EncounterNotFound` | `RuntimeException` | Aucune rencontre n'a l'identifiant, ou la rencontre n'a pas ce combattant. |
| `Exception\EncounterConflict` | `RuntimeException` | La rencontre a changé depuis sa lecture : le changement n'est pas gardé, et se refait sur ce qu'on relit. |
