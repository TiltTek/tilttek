# Exceptions

Every exception of the library implements `Exception\HandoutException`: catch it to catch them all.

| Exception | Extends | Thrown when |
|---|---|---|
| `Exception\InvalidHandout` | `InvalidArgumentException` | A value is out of bounds: a title, a text, an attachment, a media type, a cue, an audience or a limit |
| `Exception\UnknownHandout` | `OutOfBoundsException` | No handout holds the reference |
| `Exception\HandoutRefused` | `DomainException` | An act the rules refuse, with its `Refusal` in `$refusal` |

A value is checked before the store is read: an invalid handout is refused as `InvalidHandout` even when its reference
is taken.

## Refusals

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

The messages are in English for logs; an application translates the `Refusal` value, `already_created` and so on, for
its users.
