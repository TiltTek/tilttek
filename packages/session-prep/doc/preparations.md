# Preparations

## A preparation

A `Preparation` is the game master's preparation of one session of a campaign:

| Value | Bounds | Meaning |
|---|---|---|
| `id` | an identifier | the preparation |
| `campaign` | an identifier | the campaign, which a change never moves |
| `gameMaster` | an identifier | the member who prepares it and changes it |
| `title` | one line, 1 to 200 characters | what the game master calls it |
| `session` | an identifier, or null by default | the session it is for, in the application's schedule |
| `scenes` | 0 to 50 `Scene` | the scenes that might happen, in their order |
| `secrets` | 0 to 50 `Secret` | what the players may learn, with its clues |
| `characters` | 0 to 100 `NonPlayerCharacter` | the characters the game master plays |
| `places` | 0 to 50 `Place` | the places the characters may go |
| `notes` | 0 to 100 `Note` | free notes |
| `checklist` | 0 to 50 `ChecklistItem` | the steps of the preparation |
| `status` | a `PreparationStatus`, `Draft` by default | being written, ready or closed |
| `version` | 0 or more, 0 by default | 0 for a new preparation; the store's version otherwise |

An identifier is 1 to 128 letters, digits, `_`, `.`, `:` or `-`, starting with a letter: an opaque reference, never a
name or an address. The session's identifier is the one the application's scheduler gives, such as a session of
`tilttek/session-scheduler`; the library reads nothing from it.

## Its parts

Each part implements `Part`: its `key`, 1 to 64 letters, digits, `_`, `.`, `:` or `-` starting with a letter, is unique
among the parts of its kind in the preparation, and names it in every call.

| Part | Values |
|---|---|
| `Scene` | `title`, one line of 1 to 200 characters; `summary`, lines of at most 4000; `characters`, the keys of at most 20 characters it features; `places`, the keys of at most 10 places it happens in; `status`, a `SceneStatus`: `Planned`, `Played` or `Skipped` |
| `Secret` | `text`, lines of 1 to 1000 characters; `clues`, at most 10 `Clue`; `revealed` |
| `Clue` | `text`, one line of 1 to 500 characters — a letter, a rumour, a track —; `found` |
| `NonPlayerCharacter` | `name`, one line of 1 to 120 characters; `role`, one line of at most 200 — what they want, what they do —; `notes`, lines of at most 2000; `reference`, the identifier of their entry elsewhere, or null |
| `Place` | `name`, one line of 1 to 120 characters; `description`, lines of at most 2000; `reference`, the identifier of its entry elsewhere, or null |
| `Note` | `text`, lines of 1 to 4000 characters |
| `ChecklistItem` | `label`, one line of 1 to 200 characters; `required`, whether the preparation waits for it before it is ready; `checked` |

A text is valid UTF-8, trimmed, and counted in characters. One line holds no control character; lines may hold line
breaks and tabulations, their ends written `\n`. No text holds the characters that embed, override or isolate the
direction of a text. A scene features only the characters and places its preparation holds.

A secret is tied to no scene: it comes out wherever it fits, and a clue found does not reveal it, the game master says
when it is.

## Changing a preparation

A preparation is a value: each method returns a new one, checked as a whole, and leaves the first as it was.

| Method | Returns the preparation |
|---|---|
| `withTitle()`, `withGameMaster()`, `withSession()`, `withStatus()`, `withVersion()` | with that value |
| `withScene()`, `withSecret()`, `withCharacter()`, `withPlace()`, `withNote()`, `withItem()` | with the part in place of the part of its key, or after the others |
| `withoutScene()`, `withoutSecret()`, `withoutNote()`, `withoutItem()` | without the part of the key |
| `withoutCharacter()`, `withoutPlace()` | without the part of the key, nor any scene featuring it |
| `moveScene($key, $before)`, `moveItem($key, $before)` | with the part moved just before another, or after every other when `$before` is null |
| `withSceneStatus()`, `withSecretRevealed()`, `withClueFound()`, `withItemChecked()` | with that fact |
| `withFactsOf($other)` | with each fact as the part of its key is in the other, and the rest at their start; null starts them all |

The order of a list is the order the game master gives it: there is no number to sort by. A key that no part has
raises `InvalidPreparation`, and so does a list made too long.

`scene()`, `secret()`, `character()`, `place()`, `note()` and `item()` give a part by its key, or null.
`unrevealedSecrets()` gives the secrets not revealed yet, and `missingItems()` the keys of the required items not
checked yet.

## Checklist templates

A `ChecklistTemplate` holds up to 50 items, none checked. `applyTo()` adds them after the preparation's own items,
leaving those whose key it holds already.

`ChecklistTemplate::standard()` goes through the usual steps of a preparation, each named by what it is for:

| Key | Step | Required |
|---|---|---|
| `recap` | Recall what happened in the last session | |
| `characters` | Review the player characters and what they are after | |
| `limits` | Check the content limits the table agreed on | |
| `opening` | Write an opening that starts in the action | yes |
| `scenes` | Sketch the scenes that might happen | yes |
| `secrets` | Write secrets and the clues that lead to them | yes |
| `places` | Pick the places the characters may visit | |
| `npcs` | Pick the non-player characters and what they want | |
| `threats` | Pick the threats and look up their statistics | |
| `rewards` | Pick the rewards | |
| `materials` | Gather the maps, handouts and music | |

The labels are in English; an application that shows them in another language translates them by their key, or keeps
templates of its own.
