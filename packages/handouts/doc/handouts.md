# Handouts

A handout is a document the game master prepares for the players of a table. The table is what the application plays
in — a campaign, a session, a game —, named by its reference; the library knows nothing else of it, not even who sits
at it.

## Creating a handout

```php
$handout = $handouts->create(
    ResourceReference::of('handout', '4'),
    ResourceReference::of('campaign', '3'),
    'The miller\'s letter',
    "Meet me at the mill at midnight.\nCome alone.",
    [new Attachment(ResourceReference::of('file', '9'), 'image/jpeg', 'The letter, stained with flour')],
    $gm,                                                  // the acting reference, for the events
);
```

A handout holds:

| Part | Bounds |
|---|---|
| Its title | 1 to 150 characters of UTF-8, without control characters nor surrounding spaces |
| Its text, or none | 1 to 20,000 characters of UTF-8, not blank, line breaks and tabs the only control characters |
| Its attachments | Distinct files, at most `Limits::$attachmentsPerHandout` — 10 by default, 50 at most |

It holds a text, an attachment, or both. A new handout is at revision 1 and concealed from every player. A reference
is created once (`Refusal::AlreadyCreated`), and a table holds at most `Limits::$handoutsPerTable` handouts — 200 by
default (`Refusal::TooManyHandouts`).

The text is plain text: the application renders it as text, or reads it as the source of its own format, and escapes
it where it shows it.

## Attachments and media types

An `Attachment` names a file of the application's storage by its reference, with its media type — `type/subtype` in
lowercase, without parameters — and a description of 1 to 500 characters, which stands in for the file where it cannot
be seen or heard: a screen reader, a printed sheet, a slow connection.

The library never reads, stores nor serves the file. The application checks the file's real type when it stores it,
and serves it only to a player for whom `canSee()` is true.

A handout's attachments must have a media type the application allows (`Refusal::MediaTypeNotAllowed`). `MediaTypes`
holds them; by default:

| Kind | Media types |
|---|---|
| Images | `image/png`, `image/jpeg`, `image/webp`, `image/avif`, `image/gif` |
| Documents | `application/pdf`, `text/plain` |
| Sounds | `audio/mpeg`, `audio/ogg` |
| Videos | `video/mp4`, `video/webm` |

SVG is left out: an SVG file can carry scripts. An application that sanitises its SVG files before storing them adds
`image/svg+xml` to its own list:

```php
$mediaTypes = new MediaTypes([...MediaTypes::DEFAULTS, 'image/svg+xml']);
```

## Editing a handout

```php
$handouts->edit($handout, 'The miller\'s letter, torn', 'Meet me at the mill.', [], $gm);
```

An edit replaces the title, the text and the attachments, and raises the revision. The players the handout is
revealed to see the new revision at once; those who opened an earlier one find it outdated in their view, as
[what players have seen](sightings.md) describes. The same bounds and media types apply.

## Removing a handout

`remove()` removes the handout with its disclosures, staged reveals and sightings. The files stay in the application's
storage, which removes them if no other handout points at them.

## Reading

`handout()` reads one handout, or throws `UnknownHandout`. `handouts($table)` reads every handout of a table, revealed
or not, the earliest created first: the game master's view.
