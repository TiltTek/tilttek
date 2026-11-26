# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Preparations

| Type | Role |
|---|---|
| `SessionPreparer` | The service: create, change, link, mark the facts, ready, close, reopen, carry over, delete and read. |
| `Preparation` | The preparation of one session: its title, its session, its parts and its status. |
| `PreparationStatus` | Draft, ready or closed. |
| `Part` | An element of a preparation, found by its key. |
| `Scene` | A scene that might happen, the characters and places it features, and its status. |
| `SceneStatus` | Planned, played or skipped. |
| `Secret` | Something the players may learn, its clues, and whether it is revealed. |
| `Clue` | One way to come upon a secret, and whether it was found. |
| `NonPlayerCharacter` | A character the game master plays, and their entry elsewhere. |
| `Place` | A place the characters may go, and its entry elsewhere. |
| `Note` | A free note of the game master. |
| `ChecklistItem` | A step of the preparation, required or not, and whether it is checked. |
| `ChecklistTemplate` | The steps given to a preparation as its checklist; `standard()` is a general one. |
| `PreparationQuery` | Which preparations to read: of a campaign, of a game master, for a session, in a status. |

## Ports and stores

| Type | Role |
|---|---|
| `Contract\PreparationStore` | Keeps the preparations at a version, deletes them and reads them by query. |
| `Contract\PreparationObserver` | Told of the events of a preparation. |
| `Store\InMemoryPreparationStore` | The preparations in memory, for tests. |

## Events

| Type | Role |
|---|---|
| `Event\PreparationEvent` | Every event: the preparation. |
| `Event\PreparationCreated` | A preparation was created. |
| `Event\PreparationChanged` | Its parts were changed. |
| `Event\SessionLinked` | It was linked to a session, or to none. |
| `Event\SceneMarked` | A scene was played, skipped, or planned again. |
| `Event\SecretMarked` | A secret was revealed, or hidden again. |
| `Event\ClueMarked` | A clue was found, or not. |
| `Event\ItemMarked` | An item of the checklist was checked, or not. |
| `Event\PreparationReady` | It is ready for the table. |
| `Event\PreparationReopened` | It is a draft again. |
| `Event\PreparationClosed` | It was closed. |
| `Event\PreparationCarriedOver` | What another one left over was carried into it. |
| `Event\PreparationDeleted` | It was deleted. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\SessionPrepException` | Every exception of the library. |
| `Exception\InvalidPreparation` | A value out of its shape or its bounds, or a part no preparation has. |
| `Exception\PreparationRefused` | A change the service does not allow. |
| `Exception\PreparationNotFound` | No preparation has the identifier. |
| `Exception\PreparationConflict` | The preparation changed since it was read. |

## Internal

| Type | Role |
|---|---|
| `Internal\Identifier` | The shape of an identifier and of a key, and lists of distinct keys. |
| `Internal\Text` | One line or lines of text, checked and bounded. |
| `Internal\Parts` | Lists of parts by key: distinct, found, put, removed and moved. |
