# Parties and members

## Forming a party

A party is formed for a campaign, under the reference the application gives it, with a name and, optionally, the key
of its game system:

```php
$parties->form($party, $campaign, 'The Lantern Bearers', 'srd-5.2', $by);
```

A reference is formed once: a second `form()` under it is refused with `Refusal::AlreadyFormed`. The name is 1 to 100
characters of UTF-8 without control characters nor surrounding spaces; the system key, like every key of the library,
is 1 to 96 lowercase letters, digits, `_`, `-`, or single dots between them.

`rename()` changes the name, `useSystem()` changes the system or clears it with `null` — the members keep their roles,
which the composition then reads against the new system —, and `disband()` removes the party with its members, its
purse, its loot and its notes.

## Who may act

The library decides no permission. The application checks who may act — the game master, the members, an organiser —
before it calls, and passes the acting reference as the last argument, `$by`, which the events carry. The one rule the
library keeps is that a note's author and a loot holder belong to the party.

## Members

A character joins under its own reference, active, with the keys of the roles it fills:

```php
$parties->join($party, $character, ['role.healer', 'role.scout']);
```

A member fills 0 to 5 distinct roles, and joins with none by default: roles are optional, for a game without classes
or jobs as for one that has them. A key given twice is kept once, in the order first given. Roles are never checked
against a system: a role the system does not declare is told by the composition, never refused.

A character joins a party once (`Refusal::AlreadyMember`); a party holds at most `Limits::$membersPerParty` members
(`Refusal::Full`), and a character belongs to at most `Limits::$partiesPerMember` parties
(`Refusal::TooManyParties`).

`assignRoles()` replaces a member's roles. `setStanding()` sets it `Standing::Active` or `Standing::Away`: an away
member keeps its place, its roles and the loot it carries, but receives no share of the purse by default and counts
nowhere in the composition.

`members()` reads the members, the earliest arrived first, ties in the byte order of their references; `member()` reads
one, or null; `partiesOf()` reads the parties a character belongs to, the earliest joined first.

## Leaving

`leave()` removes a member: the loot it carried goes back to the stash, the notes it wrote stay with their author.
Acts on a reference that is not a member are refused with `Refusal::NotMember`.

## Erasure

`forget($member)` erases a character from every party: it leaves each, the loot it carried goes back to the stash, and
the notes it wrote, in any party, lose their author and keep their text. Each party is told `Change::Forgotten`.
