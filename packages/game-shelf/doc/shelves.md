# Shelves

A member has one shelf, read with `GameShelves::shelf()`: a member who never kept anything has an empty shelf at
version 0. A member is a stable, opaque identifier, never a name or an address: it holds no "@". Games are the
identifiers of entries of `tilttek/game-catalog`.

Every change goes through `GameShelves`, which reads the shelf, changes it, keeps it at the next version, then tells
the observer what happened.

## Copies

A `Copy` is one box: its own identifier, its game, its statuses, the edition the member names, where it is kept and
whether it may leave the house.

| Status | Meaning |
|---|---|
| `CopyStatus::Owned` | The member owns it; the default. |
| `CopyStatus::PreviouslyOwned` | The member owned it once. |
| `CopyStatus::Preordered` | The member ordered it and waits for it. |
| `CopyStatus::ForTrade` | The member offers it for trade. |

A copy holds at least one status, each once. Owned and previously owned do not go together, and a copy for trade is
owned. The statuses are kept in the order of the table above.

```php
$shelves->place('member-1', new Copy('copy-1', 'harbour', edition: 'French', location: 'Living room'));
$shelves->place('member-1', new Copy('copy-2', 'harbour', [CopyStatus::Owned, CopyStatus::ForTrade], 'English'));
$shelves->remove('member-1', 'copy-2');
```

`place()` adds a copy, or replaces the copy of the same identifier. A new copy, or a copy given another game, is of a
game the catalogue holds and does not hide: `EntryNotFound` or `ShelfRefused` otherwise. A copy kept of a game the
catalogue hides since then may still change. The edition and the location are 1 to 100 characters on one line.

A copy can be brought to a game night when it is owned and `bringable` is true; `bringable: false` keeps a fragile or
lent box at home.

A copy an import placed is `imported`; a copy the member places is not. A copy placed over an imported copy of the
same game stays imported, so that the member may name its edition or its location; given another game, it is a copy
entered by hand. Only a synchronized import reads the mark: see [Import and export](import-export.md).

## Wishes

A `Wish` is a game the member wishes, with what they wish of it and how much.

| Kind | Meaning |
|---|---|
| `WishKind::Wishlist` | On the wishlist; the default. |
| `WishKind::WantToBuy` | The member wants to buy it. |
| `WishKind::WantInTrade` | The member wants it in a trade. |
| `WishKind::WantToPlay` | The member wants to play it. |

| Level | Collection files' priority |
|---|---|
| `WishLevel::MustHave` | 1 |
| `WishLevel::LoveToHave` | 2 |
| `WishLevel::LikeToHave` | 3 |
| `WishLevel::ThinkingAboutIt` | 4 |
| `WishLevel::DontBuy` | 5 |

A member wishes a game once. A new wish goes last of the member's order; a wish replaced keeps its place.
`moveWishBefore()` and `moveWishAfter()` move a wish to the place before or after another: the order is set by the member,
never by numbers.

```php
$shelves->wish('member-1', new Wish('lanterns', [WishKind::Wishlist, WishKind::WantToBuy], WishLevel::MustHave));
$shelves->wish('member-1', new Wish('tide'));
$shelves->moveWishBefore('member-1', 'tide', 'lanterns');
$shelves->unwish('member-1', 'lanterns');
```

`Shelf::wishes()` gives the wishes in the member's order. `Shelf::wishlist()` gives the wishlist: the wishes from
"must have" to "don't buy this", then those without a level, the member's order within each level.

## Notes

A `Note` is what the member keeps of a game for themselves, apart from the catalogue's sheet: a rating from 1 to 10,
a comment of up to 2000 characters with line breaks, up to 20 tags of 1 to 40 characters without ";", and the number
of plays, from 0 to 1 000 000. A note is of a game the catalogue holds, hidden or not; an empty note drops the note
of its game.

```php
$shelves->note('member-1', new Note('harbour', rating: 8, comment: 'Best with four.', tags: ['family'], plays: 12));
$shelves->note('member-1', new Note('harbour'));    // drops it
```

## Bounds

| Bound | Value |
|---|---|
| `Shelf::MAX_COPIES` | 5000 copies |
| `Shelf::MAX_WISHES` | 1000 wishes |
| `Shelf::MAX_NOTES` | 5000 notes |
| `Copy::MAX_EDITION`, `Copy::MAX_LOCATION` | 100 characters |
| `Note::MAX_COMMENT` | 2000 characters |
| `Note::MAX_TAGS`, `Note::MAX_TAG` | 20 tags of 40 characters |
| `Note::MAX_PLAYS` | 1 000 000 plays |

A full shelf takes nothing new, but still replaces what it holds. Every text is valid UTF-8, trimmed, and holds no
control character — but the line breaks of a comment — and none of the characters that reverse the direction of a
text.

## Visibility and views

`share()` shows the shelf and the wishlist, each to nobody (`Visibility::Private`, the default), the signed-in
members (`Visibility::Members`) or anyone (`Visibility::Public`).

`view()` gives a `ShelfView` for a viewer — `Viewer::Owner`, `Viewer::Member` or `Viewer::Anyone`, as the application
decides who a visitor is:

| Part | The member | Another viewer |
|---|---|---|
| Copies | All, with their location | When the shelf is shown to them, without their location |
| Wishlist | All, in the order of the wishlist | When the wishlist is shown to them |
| Notes | All | Never |

The application renders a view, never the shelf itself, to whoever is not its member.

## The games a member can bring

`bringable()` gives the games the members named own and can bring to a game night, as `BringableGame`: once per
member and game, however many copies they hold, member after member in the order given, up to the limit. It reads at
most 2000 members and gives at most 10 000 games at once. [Extension points](extension-points.md#a-game-nights-shelves)
shows the adapter of a game night's port.
