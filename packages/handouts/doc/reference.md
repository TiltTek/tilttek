# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Handouts` | Creates, reveals, stages, conceals, reads and erases handouts and what players saw |
| `Handout` | A handout: reference, table, title, text, attachments, revision, moments |
| `Attachment` | A file of the application's storage: reference, media type, description |
| `MediaTypes` | The media types attachments may have |
| `Audience` | Everyone at the table, or players named |
| `Disclosure` | A handout revealed to everyone or to a player: revision shown, moment, acting reference |
| `Trigger` | A moment or a cue |
| `StagedReveal` | A reveal prepared ahead for everyone or a player, on its trigger |
| `Sighting` | What a player opened of a handout: revision, first and last moments |
| `Visible` | A handout as a player finds it: earliest reveal, freshness, sighting |
| `Freshness` | `Unseen`, `Outdated` or `Seen` |
| `Limits` | Handouts per table, attachments per handout, audiences per handout, reveals per release |
| `HandoutChanged` | Told after each act, identifiers only |
| `Change` | What happened to a handout |
| `Refusal` | Why an act is refused |
| `Contract\HandoutStore` | Where handouts and what they hold are kept |
| `Contract\HandoutObserver` | What the application does with the events |
| `Store\InMemoryHandoutStore` | The store in memory |
| `Exception\HandoutException` | Every exception of the library |
| `Exception\HandoutRefused` | An act refused, with its `Refusal` |
| `Exception\UnknownHandout` | No handout under a reference |
| `Exception\InvalidHandout` | A value out of bounds |

`Text` is internal: it checks the keys, titles and texts, and is not part of the promised API.
