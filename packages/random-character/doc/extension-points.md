# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Declare a system's creation rules | Write documents of the [format](format.md) and read them with `Format\RulesReader`, or build `CreationRules` in code. |
| Give the attributes and their bounds | Pass the `Systems` of `tilttek/rpg-systems` to `CharacterRoller`. |
| Give the tables the fields draw from | Pass a `Generator` of `tilttek/generator` built on the application's lists and generators. |
| Bound or extend the dice | Pass a `DiceRoller` of `tilttek/dice`, with its limits and custom dice. |
| Give the randomness of drawn seeds | Pass a `Random\Randomizer`: a secure engine in production, a fixed engine in tests. |
| Act on a roll | Implement `Contract\CharacterObserver`, and pass it to `CharacterRoller`. |
| Keep characters | Store `RandomCharacter::toArray()` with the sheet, or only the rules' key and the seed when nothing was edited. |
| Show where a character's text comes from | Show `RandomCharacter::$attributions` with it, as `tilttek/generator` describes them. |

## The observer and its event

`Contract\CharacterObserver::notify()` is told of each character rolled, as an `Event\CharacterRolled`: the key of the
rules, the key of the system, and whether the caller gave the seed. An application sends a message from it, with those
identifiers alone. [Rolling](rolling.md#the-observer) gives the details.

## The rules are data

Rules are plain data: the library runs no code of the application's. What they may say is a closed list — three methods
for the stats, generated and rolled fields, placeholders that read earlier fields. Any logic beyond it lives in the
generators, whose Twig templates run in the sandbox of `tilttek/generator` within its budget, or in the application,
which reads the character and acts.

## Where the library stops

- It does not keep characters, rules or tables: the application stores them and builds the catalogue, the systems and
  the generator from them.
- It does not enforce the system's bounds on a hand edit: a character sheet does, when it takes the character over.
- It does not show a character: the application renders its sheet.

## The services

`CharacterRoller` and `Format\RulesReader` are read-only and keep nothing between two calls: one instance serves every
request of a worker. `CreationRules`, `RulesCatalogue` and `RandomCharacter` are immutable values that requests share.
