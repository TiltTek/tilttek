# Guilds and members

A guild is a lasting community of members, named by the reference the application gives it — `guild:44`,
`club:0192f3c4-…` —, with its name, its admission, its capacity and its metadata. `Guilds` is the service that runs
them on a `Contract\GuildStore`.

## Founding

```php
$guilds->found(
    ResourceReference::of('guild', '44'),
    'The Lantern Bearers',
    $founder,
    Admission::OnRequest,                       // Admission::Open by default
    20,                                         // 50 by default
    new Metadata(['motto' => 'Light the way']),
);
```

The founder is the guild's first member. A reference is founded once: founding it again is refused with
`Refusal::AlreadyFounded`.

| Value | Bounds |
|---|---|
| Name | 1 to 100 characters of UTF-8, no control character, no space around |
| Capacity | 2 to 1000 members, 50 by default |
| Metadata | at most 20 entries; a name of 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter; a value of at most 500 bytes of UTF-8 without control characters |

The library reads none of the metadata: it is the application's, to show a motto, a banner's identifier or a language.

## Admission

| Admission | `join()` returns | What follows |
|---|---|---|
| `Admission::Open` | a `Membership` | The member is in the guild at once. |
| `Admission::OnRequest` | a `JoinRequest` | The founder or an administrator calls `approve()` or `decline()`; the member may `withdraw()` their request. |

`requests($guild, $by)` lists the waiting requests, the oldest first, for the founder and the administrators only.
Changing a guild from on request to open leaves its waiting requests to be approved or declined.

## Roles

| Role | May |
|---|---|
| `Role::Founder`, one per guild | everything below; promote and demote; hand the guild over; dissolve it |
| `Role::Administrator` | approve and decline requests; remove members; update the guild; open and close goals |
| `Role::Member` | leave; contribute to the guild's goals |

```php
$guilds->promote($guild, $member, $founder);   // member → administrator
$guilds->demote($guild, $member, $founder);    // administrator → member
$guilds->transfer($guild, $heir, $founder);    // the heir becomes the founder, the founder an administrator
$guilds->remove($guild, $member, $by);         // the founder removes anyone else, an administrator members only
$guilds->leave($guild, $member);               // the founder hands the guild over first
$guilds->dissolve($guild, $founder);           // the guild, its members and its requests removed
```

An act the role does not allow is refused with `Refusal::NotAllowed`; an act by, or on, someone outside the guild with
`Refusal::NotMember`.

## Capacity and limits

A guild full refuses whoever joins and every approval with `Refusal::Full`. Lowering its capacity below the members it
holds is refused with `Refusal::BelowMembers`: the library never removes anyone to make room, and never removes a
member for being inactive.

`Limits` bounds what the reads cover:

| Limit | Default | Bounds | Refusal |
|---|---|---|---|
| Guilds a member belongs to or asked to join | 10 | 1 to 100 | `Refusal::TooManyGuilds` |
| Waiting requests of a guild | 100 | 1 to 1000 | `Refusal::TooManyRequests` |
| Open goals of a guild | 10 | 1 to 100 | `Refusal::TooManyGoals` |

The capacity is checked before the membership is written: an application serving concurrent requests runs each act in
a transaction, or under a lock on the guild, so that two members never take the last place together.

## Reading

| Method | Returns |
|---|---|
| `guild($guild)` | The `Guild`, or `UnknownGuild` |
| `membership($guild, $member)` | The `Membership`, or null |
| `isMember($member, $guild)` | Whether the member belongs to the guild |
| `members($guild)` | The memberships: the founder, then the administrators, then the members, each the earliest entered first |
| `references($guild)` | The members' references, the earliest entered first |
| `guildsOf($member)` | The member's memberships, the earliest entered first |

Members who entered at the same moment are read in the byte order of their references.

## What is told

Each act tells the observers a `GuildChanged`: the guild, the `Change`, the member it concerns, the member who acted, and
the moment — identifiers only.

| Change | Member | Actor |
|---|---|---|
| `Founded` | the founder | none |
| `Joined`, `Requested`, `Withdrawn`, `Left` | the member | none |
| `Approved`, `Declined`, `Removed`, `Promoted`, `Demoted` | the member | the founder or administrator |
| `Transferred` | the new founder | the former founder, none after an erasure |
| `Updated` | none | the founder or administrator |
| `Dissolved` | none | the founder, none after an erasure |

## Erasure

`erase($member)` removes the member's waiting requests and memberships. A guild they founded passes to its earliest
administrator, else to its earliest member, and is dissolved when they were its only member. Their points in the
guild's goals stay, without them: see [the group track](goals.md#erasure).
