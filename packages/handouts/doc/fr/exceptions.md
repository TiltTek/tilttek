<!-- phil-dev source-sha256=2d01ff5f781e999fbc88f39b8c906fd3f473ec4129bc1cc7851e8214475eba81 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\HandoutException` : attrapez celle-ci pour les attraper
toutes.

| Exception | Étend | Levée quand |
|---|---|---|
| `Exception\InvalidHandout` | `InvalidArgumentException` | Une valeur est hors des bornes : un titre, un texte, une pièce jointe, un type de média, un signal, une audience ou une limite |
| `Exception\UnknownHandout` | `OutOfBoundsException` | Aucun document ne porte la référence |
| `Exception\HandoutRefused` | `DomainException` | Un acte que les règles refusent, avec son `Refusal` dans `$refusal` |

Une valeur est vérifiée avant la lecture du dépôt : un document invalide est refusé en `InvalidHandout` même quand sa
référence est déjà prise.

## Refus

| `Refusal` | Message |
|---|---|
| `AlreadyCreated` | A handout already holds this reference. |
| `TooManyHandouts` | The table holds as many handouts as allowed. |
| `TooManyAttachments` | The handout holds more attachments than allowed. |
| `MediaTypeNotAllowed` | An attachment has a media type the application does not allow. |
| `AudienceTooLarge` | The audience names more players than allowed. |
| `TooManyRevealed` | The handout is revealed to as many audiences as allowed. |
| `TooManyStaged` | The handout holds as many staged reveals as allowed. |
| `NotRevealed` | The handout is not revealed to this player. |

Les messages sont en anglais, pour les journaux ; une application traduit la valeur du `Refusal`, `already_created` et
ainsi de suite, pour ses utilisateurs.
