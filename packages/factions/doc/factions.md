# Factions and standings

## The roster

A `Roster` holds the factions of one campaign, by its identifier, and the identifier of its game master. It holds the
`Scale` its standings use, up to 64 factions in the order given, and up to 512 relations between them. It is checked as
a whole each time it is built: each faction key once, each standing on the scale, each relation between two factions of
the roster, each pair once. Each change returns a new value; the store raises its version at each write.

Identifiers — of a campaign, a member, a character, a session — are 1 to 128 letters, digits, `_`, `.`, `:` or `-`,
starting with a letter: they are opaque, never a name or an address. Keys — of a faction, a rank, a tier — follow the
same shape with at most 64 characters, and are unique within their roster or faction.

## The scale and its tiers

A `Scale` runs from a lowest to a highest whole number, both between -100 and 100, and has a start: where a subject
stands when no change has touched them yet. Its 1 to 12 `Tier`s cut it from the lowest up: the first starts at the
lowest standing, each next one higher, none past the highest. A tier holds every standing from its own start up to the
next tier's.

`Scale::standard()` runs from -3 to 3 and starts at 0: hostile at -3, unfriendly from -2, neutral at 0, friendly from 1,
allied at 3. A campaign that counts reputation in hundreds of points, or in five named steps, builds its own:

```php
new Scale(0, 100, 20, [
    new Tier('unknown', 'Unknown', 0),
    new Tier('known', 'Known', 20),
    new Tier('trusted', 'Trusted', 60),
    new Tier('honoured', 'Honoured', 90),
]);
```

`Scale::tier()` gives the tier of a standing, `contains()` says whether a standing is on the scale, and `clamp()` brings
one back within it. `Factions::changeScale()` changes the scale of a roster only when every standing set lies within the
new one: the game master moves the others first, each with its reason.

## Factions

A `Faction` is found by its key and holds:

- a name of one line, up to 120 characters, and a summary of up to 4 000 characters, possibly empty;
- up to 16 `Rank`s, from the lowest to the highest, each with a key and a title;
- up to 64 `Standing`s, each subject once;
- up to 64 `Membership`s, each character once, each holding a rank of the faction;
- whether it is secret.

Texts are valid UTF-8, trimmed, and hold no character that reverses the direction of a text; a line holds no control
character, a summary may hold line breaks and tabulations.

`Factions::addFaction()` adds a faction after the others; it joins without standings or members, which only the calls
that take a reason set. `changeFaction()` changes the name, the summary, the ranks and the secrecy of a faction, and
keeps its standings and members as they are kept; a change that removes a rank a member holds is refused.
`removeFaction()` removes a faction and its relations; its history stays in the store until the roster is deleted.

## Standings

A `Subject` is the party as a whole, `Subject::party()`, or one character, `Subject::character('char-1')`. Its key,
`party` or `character:` followed by the identifier, is what a store keeps and what the events carry.

`Roster::standingOf()` gives where a subject stands with a faction: the standing set, or the start of the scale.

- `Factions::shift()` moves a standing by 1 to 200 steps, up when positive, down when negative. A move past an end of
  the scale stops there.
- `Factions::setStanding()` sets a standing outright; a standing off the scale is refused.

A shift or a setting that leaves the standing where it was keeps nothing, records nothing and tells nothing.

## Ranks

`Factions::setRank()` gives a character a rank of the faction, by its key: they join when they held none, rise or fall
when they held another. A null rank takes them out of the faction. `Faction::outranks()` says whether one rank stands
above another, from their order in the faction.

## Relations

A `Relation` says how two factions stand with each other, the same both ways, with a `Stance` — allied, friendly,
neutral, rival, hostile or war —, a note of up to 200 characters, possibly empty, and whether it is secret. The pair is
kept with the lower key first, whatever order it was given in; `Roster::relation()` finds it in either order.

`Factions::relate()` sets the relation of a pair in place of the one it had; `endRelation()` ends it. A faction has no
relation with itself.

## Reasons and history

A `Reason` holds the game master's words, one line of 1 to 500 characters, and the identifier of the session when one
is named. Every call that moves a standing, a rank or a relation takes one, and the store keeps an entry of the history
in the same write as the roster:

| Entry | Holds |
|---|---|
| `History\StandingEntry` | The subject, the standing before (the start of the scale when none was set) and after. |
| `History\RankEntry` | The character, the rank before and after, null when they were not a member or left. |
| `History\RelationEntry` | The pair, the relation before and after, null when there was none or it ended. |

Every entry also holds the campaign, the faction, the reason, the member who made the change and when, from the clock.
`Factions::history()` gives the entries that concern a faction, the newest first, up to 500 at once and 100 by default;
a relation's entry concerns both its factions. Changes to the scale, the names, the ranks offered and the secrecy are
told to the observer and leave no entry.

`Factions::create()` takes a roster with the standings, members and relations the campaign starts with: they are where
it begins, and leave no history.

## Secrets and views

`Factions::view()` shows the roster to its game master: every faction, each with the standing of the party first — set
or at the start of the scale — then every standing set with a character, every member, and every relation.

`Factions::playerView()` shows what the players know:

- the factions that are not secret, in the order given;
- for each, the standing of the party and, when a character is named, that character's standing and rank — never
  another character's;
- the relations that are not secret, between two factions shown.

The service does not know who plays in the campaign: the application checks that the reader is one of its players, and
names their character. Both views give `toArray()` for a page, with each standing's value, tier key and tier label; a
page that hides the numbers shows the labels alone.
