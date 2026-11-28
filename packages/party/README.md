# tilttek/party

**The party of a campaign: its members and their roles, the purse, loot and notes they share, and how its composition
compares with the roles the game system declares.**

tilttek/party keeps the group of characters that plays a campaign together. The application forms a party for a
campaign under a reference it gives, `party:7`, with the key of its game system, `srd-5.2`; characters join it under
their own references, `character:12`, each with the roles it fills — healing, defence, damage — by the keys the system
declares them under, or with none: roles are optional, and a game without classes or jobs plays with no role at all. A
member who sits out a few sessions is set away: it keeps its place, its roles and the loot it
carries, and counts nowhere until it is back.

What the party holds in common lives beside its members. Its **purse** holds whole coins by currency — `gp`, `sp`,
`credits` — never real money; a deposit or a withdrawal never makes it negative, and a **split** divides a currency
evenly among the active members, or among the members chosen, the remainder kept in the purse. Its **loot** is a list
of entries under keys, each with a quantity, carried by a member or lying in the stash. Its **notes** are the leads,
names and plans its members write down for the others.

Its **composition** is read against the roles of a system — a `RoleCatalogue` built from the party roles of a
`tilttek/rpg-systems` system, or given by the application — and told as advice, never as a refusal: which roles are
missing, which are crowded beyond their fair share, which members fill no role of the system, and which roles members
fill that the system does not declare. Roles are only a help: a system or a site that declares none gets an empty
catalogue, and its composition gives no advice.

It is for a campaign manager, a virtual table or a game site. It is a plain PHP library whose dependencies are
`phil/resource`, the opaque reference of a party, a campaign or a member, `psr/clock`, and `tilttek/rpg-systems`, whose
terms give the roles. Members are references the application gives: the library never reads a name, an e-mail or an
account, and its events carry identifiers only.

## Status

| Part | State |
|---|---|
| `Party`, `Member`, `Standing`: parties of a campaign, their members, roles and standing | Implemented and tested |
| `Parties`: form, rename, change system, disband, join, leave, assign roles, set standing, read, erase | Implemented and tested |
| `Purse`, `Split`: the shared coins, deposits, withdrawals, shares and their distribution | Implemented and tested |
| `LootItem`: shared loot, its quantity, its holder or the stash | Implemented and tested |
| `Note`: the notes members share, with their author and moments | Implemented and tested |
| `Composition\RoleCatalogue`, `Composition\CompositionAdvisor`, `Composition\Composition`: the composition against the system's roles | Implemented and tested |
| `Contract\PartyStore`, with `Store\InMemoryPartyStore` | Implemented and tested |
| `Contract\PartyObserver`, `PartyChanged`: what is told, identifiers only | Implemented and tested |
| `Limits`: members per party, parties per member, loot entries and notes per party | Implemented and tested |
| A Symfony bundle: Doctrine store, services, forms and translations | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Parties and members](doc/parties.md) | Forming, joining, roles, standing, leaving, disbanding and erasure. |
| [What the party shares](doc/shared.md) | The purse and its shares, the loot and its holders, the notes. |
| [Composition](doc/composition.md) | Roles from a system, the fair share, missing and crowded roles. |
| [Extension points](doc/extension-points.md) | The store, observers, limits and the clock. |
| [Exceptions](doc/exceptions.md) | `PartyException` and its exceptions, with the refusals and their messages. |
| [Testing](doc/testing.md) | The library's tests, and testing code that uses parties. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/party
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Party\Composition\RoleCatalogue;
use Tilttek\Party\Parties;
use Tilttek\Party\Store\InMemoryPartyStore;

$parties = new Parties(new InMemoryPartyStore(), $clock);

$party = ResourceReference::of('party', '7');
$ada = ResourceReference::of('character', '12');
$bo = ResourceReference::of('character', '13');

$parties->form($party, ResourceReference::of('campaign', '3'), 'The Lantern Bearers', 'srd-5.2');
$parties->join($party, $ada, ['role.healer']);
$parties->join($party, $bo, ['role.healer']);

$parties->deposit($party, 'gp', 251);
$parties->distribute($party, 'gp')->share;            // 125 each, 1 left in the purse
$parties->addLoot($party, 'loot.rope', 'Silk rope', 1, $bo);
$parties->writeNote($party, 'lead.mill', $ada, 'The miller lies.');

$composition = $parties->composition($party, RoleCatalogue::fromSystem($system));
$composition->missing();                              // the roles no active member fills, none for a game without roles
```

## Requirements

PHP 8.5 or later, with `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
