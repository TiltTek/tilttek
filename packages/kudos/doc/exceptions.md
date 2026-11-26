# Exceptions

Every exception of the library implements `Exception\KudosException`: catch this one to catch them all.

| Exception | Thrown when | Message |
|---|---|---|
| `KudosRefused` | Thanks cannot be given; `refusal` says why | `A member cannot thank themselves.`, `This member does not take thanks.`, `The giver has given all the thanks of their day.`, `The giver has thanked this member as often as the period allows.` |
| `UnknownMessage` | The message is not in the application's list | `"…" is not one of the messages thanks are given with.` |
| `InvalidKudos` | A message, a list, limits, an object reference or a page out of bounds; an activity read as thanks that holds no message | What is out of bounds, and the bound |

`KudosRefused` extends `DomainException`; `UnknownMessage` and `InvalidKudos` extend `InvalidArgumentException`.

The exceptions of `tilttek/activity` reach the caller unchanged: `IdempotencyConflict` when a key was given to other
thanks, `InvalidActivity` when a key the application gives is not an idempotency key.
