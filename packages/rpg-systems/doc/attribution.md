# Sources and attribution

## Provenances

A `Provenance` says where a record comes from:

| Field | What it holds |
|---|---|
| `title` | The title of the source, required |
| `licence` | Its `Licence`, required |
| `attribution` | The statement a work that shows the material displays: required when the licence asks for attribution |
| `author` | Who holds the source |
| `url` | Where it is published: an absolute http or https address, read by `phil/url` |

Every record carries one, and two equal provenances share an `identity()`, so that each source is shown once. The
attribution is the text the source asks for, given by whoever states the provenance: the library does not write it.

## Licences

The list of licences is closed; material under any other licence is refused when it is read.

| `Licence` | Identifier | Asks for attribution | Reciprocal |
|---|---|---|---|
| `CcBy40` | `CC-BY-4.0` | Yes | No |
| `CcBy30` | `CC-BY-3.0` | Yes | No |
| `CcBySa40` | `CC-BY-SA-4.0` | Yes | Yes |
| `Cc0` | `CC0-1.0` | No | No |
| `Orc` | `ORC` | Yes | Yes |
| `Owned` | `owned` | No | No |

`title()` and `url()` give the licence's name and the page of its text; `Owned`, the application's own material, has
no page. `isReciprocal()` says whether a work that adapts the material passes the material on under the same licence.

## The notices of a page

`AttributionRenderer` gives the notices a page displays: one for each source whose licence asks for attribution, each
once, in the order the sources first appear.

```php
$renderer = new AttributionRenderer();

$renderer->text($system);               // one line per source
$renderer->html($system, 'es');         // an HTML list, with the Spanish translation's source
$renderer->sources($system);            // the provenances themselves
```

A page that shows a few records rather than a whole system gives their provenances: `$renderer->html([$term->provenance,
$attribute->provenance])`. The locale chooses the translations whose sources are shown — those of the locale and of its
language —; without one, every translation's source is. `all: true` lists the sources that ask for no attribution too.

A line holds the attribution, or the title of a source without one — followed by its address when it has one —, then
the licence's name and the page of its text:

```text
This work includes material from the System Reference Document 5.2 … (Creative Commons Attribution 4.0 International: https://creativecommons.org/licenses/by/4.0/)
```

The HTML list is `<ul class="attribution">`, every text escaped, the licence's name linked to its page with
`rel="license"`; it is an empty string when no source asks for attribution, so that a page prints nothing.
