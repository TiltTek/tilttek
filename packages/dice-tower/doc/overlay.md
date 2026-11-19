# The overlay

```twig
{{ dice_tower('table:7', {'member:42': 'Aelis', 'member:43': 'Brom'}) }}
{{ dice_tower(table, names, title: 'The tower', announce: false) }}
```

`dice_tower(table, names, title, announce)` draws a table's tower for a viewer the application's `TableAccess` allows,
and nothing for another. The table is a `ResourceReference` or its string. The names are those of the rollers the page
may show, by reference: they reach the viewer's page only. The title replaces the translated one. With `announce` false,
the list is not a live region: an accessible dice interface on the same page announces the rolls instead.

Drawing the overlay gives the viewer the subscription to the table's topic, in the authorization cookie of the
response.

## What it draws

A card of the UX kit, its title, and the last entries of the tower as an ordered list — a `log` region that screen
readers read as it grows, unless `announce` is false. Each entry gives the roller's name when the page knows it, each
instruction's expression with its total or what it gave, and every die: a face of a custom die by its label, the words
a screen reader says, and a die that did not count followed by "not counted", not only by a style. An empty tower says
so, and a status line says when the connection is lost.

The classes are hooks for the application's stylesheet:

| Class | Element |
|---|---|
| `tilttek-dice-tower` | The card |
| `tilttek-dice-tower__entries` | The list |
| `tilttek-dice-tower__entry` | An entry, with its `data-position` |
| `tilttek-dice-tower__roller` | The roller's name |
| `tilttek-dice-tower__result` | An instruction |
| `tilttek-dice-tower__dice`, `tilttek-dice-tower__die`, `is-dropped` | The dice, a die, a die that did not count |
| `tilttek-dice-tower__empty`, `tilttek-dice-tower__status` | The empty tower, the state of the connection |

## The Stimulus controller

The controller `tilttek--dice-tower--tower` subscribes to the table's topic, with the cookie of the viewer, and adds
each new entry once and in order. When the subscription opens — the first time, and each time it comes back — it reads
the entries after the last position it shows from the route of the missed entries, holding the live entries back until
they are read; then it adds them all, without duplicates. It keeps the last 200 entries on the page.

| Value | What it holds |
|---|---|
| `hub` | The URL to subscribe to; none without a hub |
| `entries-url` | The route of the missed entries; none without the route |
| `last` | The last position the page shows |
| `faces` | The labels of the faces of the custom dice, by die and face |
| `names` | The names of the rollers, by reference |
| `dropped`, `offline` | The words of a die that did not count, and of a lost connection |
| `limit` | How many entries the page keeps, 200 by default |

## Its event

Each new entry is dispatched on the element as `tilttek--dice-tower--tower:drop`, which bubbles to the window, with:

| Detail | What it holds |
|---|---|
| `entry` | The entry, as the hub pushed it |
| `view` | What the overlay shows of it: the name, and each instruction with its dice by label |
| `text` | The entry in one sentence: `Aelis: 2d6+1 = 8 (4, 3)` |

An accessible dice interface announces it, a sound board plays a sound, a counter counts:

```html
<div data-action="tilttek--dice-tower--tower:drop@window->my-listener#handle"></div>
```
