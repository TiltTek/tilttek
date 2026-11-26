<!-- phil-dev source-sha256=f47ccf30aad165f82be292c866bc29704240b69a776c228d1578204d8cef85a0 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\TurnClockException` : interceptez-la pour les intercepter
toutes.

| Exception | Étend | Quand |
|---|---|---|
| `Exception\InvalidClock` | `InvalidArgumentException` | Une valeur sort de sa forme ou de ses bornes : un identifiant, un budget, un incrément, trop peu ou trop de joueurs, un joueur en double, un budget pour un joueur absent de l'horloge, des vacances trop longues ou qui en chevauchent d'autres, plus de 8 vacances, un changement de temps nul ou trop grand. |
| `Exception\ClockRefused` | `DomainException` | Un changement non permis sur l'horloge dans son état : démarrée deux fois, non démarrée, arrêtée, un tour passé par un joueur qui ne le détient pas, un joueur nommé qui est hors temps ou qui est celui qui passe, un joueur hors temps qui commence, des vacances déjà finies, un identifiant pris. |
| `Exception\ClockNotFound` | `RuntimeException` | Aucune horloge n'a l'identifiant, ou l'horloge n'a pas ce joueur. |
| `Exception\ClockConflict` | `RuntimeException` | L'horloge a changé depuis sa lecture : le changement n'est pas gardé, et se refait sur ce qu'on relit. |
