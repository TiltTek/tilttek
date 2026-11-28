# Composition

The composition of a party compares its active members with the roles a game system declares. It is advice: the
library refuses no member, no role and no party for it, and the application shows it as it likes — a hint to the game
master, a badge on the party sheet. Roles are only a help: a party plays as well without any, and a game without
classes or jobs has none.

## The roles of a system

A `RoleCatalogue` holds 0 to 50 roles with distinct keys, each a `Role` with a name of 1 to 100 characters and a
minimum, the fewest members a balanced party has in it: 1 by default, 0 for a role that is welcome but never missing,
at most 10.

A system of `tilttek/rpg-systems` declares the roles of a party as its party roles, in its order:

```php
use Tilttek\Party\Composition\Role;
use Tilttek\Party\Composition\RoleCatalogue;

$roles = RoleCatalogue::fromSystem($system);                         // the party roles of the system
$roles = RoleCatalogue::fromSystem($system, locale: 'fr', minimum: 0);
$roles = new RoleCatalogue([new Role('healer', 'Healer'), new Role('scout', 'Scout', minimum: 0)]);
$roles = RoleCatalogue::none();                                      // a game without roles
```

`fromSystem()` names each role in the locale given, falling back to the role's English name, and gives every role the
same minimum; a system without party roles gives an empty catalogue, and `isEmpty()` tells it. The open sources the
import profiles of `tilttek/rpg-systems` read declare no party roles, and the library invents none: an application
that wants roles for such a system declares its own, by hand.

## A game without roles

Against an empty catalogue there is nothing to advise. The composition holds no coverage, no unassigned member and no
unknown role — the roles members may carry are free labels then —, `isBalanced()` is true, `fairShare` is 0, and
`hasAdvice()` is false, so the application shows no hint at all:

```php
$composition = $parties->composition($party, RoleCatalogue::fromSystem($system));

if ($composition->hasAdvice()) {
    // show the missing and crowded roles
}
```

## Reading a composition

```php
$composition = $parties->composition($party, $roles);
// or, on members the application already holds:
$composition = new CompositionAdvisor()->advise($members, $roles);
```

Each role of the catalogue gets a `RoleCoverage`: the active members who fill it, in the order of the party, and a
`Coverage`:

- `Missing` when fewer active members fill it than its minimum;
- `Crowded` when more fill it than its fair share — the active members divided among the roles, rounded up, and never
  below the role's own minimum;
- `Covered` otherwise.

With four active members and three roles, the fair share is two: a third healer makes healing crowded. With one role,
no role is ever crowded. A member who fills two roles counts in each.

`Composition` tells:

| Read | What it holds |
|---|---|
| `coverages` | Every role of the catalogue, in its order |
| `missing()`, `crowded()` | The roles below their minimum, above their fair share |
| `hasAdvice()` | False against a catalogue without roles |
| `isBalanced()` | True when no role is missing nor crowded |
| `coverageOf($key)` | The coverage of one role, or null |
| `unassigned` | The active members who fill no role of the catalogue |
| `unknownRoles` | The roles members fill that the catalogue lacks, by key, with their members |
| `active`, `fairShare` | The active members counted, and the even share before the minimums, 0 without roles |

Away members count nowhere: neither in a role, nor among the unassigned, nor in the fair share.

## Cost

`CompositionAdvisor` keeps no state. Its work grows with the members times their roles — at most 100 members of 5
roles each — and with the roles of the catalogue, at most 50.
