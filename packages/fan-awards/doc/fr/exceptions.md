<!-- phil-dev source-sha256=20039912426dbf96c380f001a78ece241f17eea566eb024bc0830d881db8d1d5 -->
# Exceptions

Toutes les exceptions de la bibliothèque implémentent `Exception\FanAwardsException` : attrapez celle-ci pour les
attraper toutes.

| Exception | Lancée quand | Message |
|---|---|---|
| `AwardRefused` | Les règles des prix refusent l'acte ; `refusal` dit pourquoi, `reason` contient la raison de l'admissibilité | Le message du refus, ci-dessous, puis la raison |
| `UnknownAward` | Aucune édition ne porte la clé, ou l'édition n'a aucune catégorie de cette clé | `No edition is known as "…".`, `The edition "…" holds no category "…".` |
| `InvalidAward` | Une édition, une catégorie ou un candidat hors bornes ; une méthode de finale qui n'est pas classée ; un bulletin qui enfreint les règles de sa phase | Ce qui est hors bornes ; pour un bulletin, la règle de `phil/poll` qu'il enfreint |

`AwardRefused` étend `DomainException`, `UnknownAward` `OutOfBoundsException`, `InvalidAward`
`InvalidArgumentException`.

| `Refusal` | Message |
|---|---|
| `EditionExists` | An edition already holds this key. |
| `WrongPhase` | The edition is not in a phase that allows this act. |
| `VoterNotEligible` | This member may not vote in this edition. |
| `Ineligible` | This nominee is not eligible in this category. |
| `TooManyCandidates` | The category holds as many nominees as a ballot may offer. |
| `NotCounted` | The edition is not counted yet. |
| `NotAnnounced` | The results are published once the winners are announced. |
