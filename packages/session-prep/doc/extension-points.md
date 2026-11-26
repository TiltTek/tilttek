# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the preparations | Implement `Contract\PreparationStore`, and pass it to `SessionPreparer`. |
| Act on what happened | Implement `Contract\PreparationObserver`, and pass it to `SessionPreparer`. |
| Give a preparation its steps | Build a `ChecklistTemplate` of your items, and apply it to a preparation. |

## The store

`Contract\PreparationStore` keeps the preparations, each at a version:

- `preparation()` gives one by its identifier, or null;
- `save()` keeps one at the next version and returns it. One at version 0 is new, and the store has none of its
  identifier; any other is kept only when the store holds it at that version, and `PreparationConflict` is raised
  otherwise, nothing kept;
- `delete()` forgets one when the store holds it at its version, and raises `PreparationConflict` otherwise, nothing
  forgotten;
- `preparations()` gives the preparations a `PreparationQuery` matches, at most the limit, by identifier.
  `PreparationQuery::matches()` states what each query means; a store reads it from `campaign`, `gameMaster`,
  `session` and `status`, by an index on each.

`Store\InMemoryPreparationStore` keeps them in memory, as long as the object lasts: it is for tests. An application
keeps them in its database: a preparation holds strings, an integer, an enumeration and its lists of parts, which hold
strings, booleans, enumerations and lists of keys, and fit a JSON column each, or rows of their own kept in their order.

## The observer and its events

`Contract\PreparationObserver::notify()` is told each fact once the preparation is kept, by identifiers only:

| Event | When | Carries |
|---|---|---|
| `Event\PreparationCreated` | A preparation was created. | |
| `Event\PreparationChanged` | The game master changed its parts. | |
| `Event\SessionLinked` | It was linked to a session, or to none. | the session, or null |
| `Event\SceneMarked` | A scene was played, skipped, or planned again. | the scene, its status |
| `Event\SecretMarked` | A secret was revealed, or hidden again. | the secret, whether it is revealed |
| `Event\ClueMarked` | A clue was found, or not. | the secret, the clue, whether it is found |
| `Event\ItemMarked` | An item of the checklist was checked, or not. | the item, whether it is checked |
| `Event\PreparationReady` | It is ready for the table. | |
| `Event\PreparationReopened` | A ready or closed preparation is a draft again. | |
| `Event\PreparationClosed` | It was closed, the session over. | |
| `Event\PreparationCarriedOver` | What another one left over was carried into it. | the preparation carried from |
| `Event\PreparationDeleted` | It was deleted. | |

Every event implements `Event\PreparationEvent`, whose `preparation` is an identifier. An application sends a message
from them — through Messenger, with the identifiers alone — and reads the rest from the store: on `SecretMarked`, what
the players learned is recorded in their knowledge of the campaign; on `PreparationClosed`, the session's log is
started from the scenes played. A secret's text never leaves the store in a message.

## Templates

A `ChecklistTemplate` is a value: an application keeps the templates of its game masters as it keeps their
preparations, as lists of items, and builds the template when a preparation is created. `ChecklistTemplate::standard()`
is a general one, whose labels an application translates by their keys.
