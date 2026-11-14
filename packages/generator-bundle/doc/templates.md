# Templates

## `tilttek_draw`

With `symfony/twig-bundle`, the function `tilttek_draw(generator, count = 1, unique = false, seed = null)` draws in a
template and gives a `DrawResult` of `tilttek/generator`:

```twig
{% set names = tilttek_draw('name.short', 5, true) %}
<ul>{% for name in names.results %}<li>{{ name }}</li>{% endfor %}</ul>

{% set inn = tilttek_draw('tavern', seed: 'today').first %}
<h2>{{ inn.name }}</h2>
<p>{{ inn.description }}</p>
```

The results are plain texts and arrays, escaped by the application's templates as any other value. The function is a
Twig runtime: the generator is built, and its catalog checked, only when a template calls it.

## The template that draws again

`@TilttekGenerator/draw.html.twig` shows the results of a draw in a list, with a button that asks the draw API for new
ones and puts them in place:

```twig
{{ include('@TilttekGenerator/draw.html.twig', {
    result: tilttek_draw('name.short', 5, true),
    url: path('tilttek_generator_draw', {generator: 'name.short'}),
    again: 'Again',
    short: 'Fewer names than asked were found.',
}) }}
```

| Variable | Meaning |
|---|---|
| `result` | The first draw, shown before any request. |
| `url` | The address of the generator in the draw API, which must open it to anyone. |
| `again` | The text of the button; `Again` by default. |
| `short` | What the status says when a draw finds fewer results than asked. |

A structured result shows its `name` field, or its JSON; an application that shows more writes its own template with
the same Stimulus controller. A paragraph of class `tilttek-generator-attribution` follows the results with their
attributions, each its attribution, or its source, then its licence in parentheses, separated by semicolons:
`Tables by A. Author (CC-BY-4.0); A grammar`.

## The Stimulus controller

`tilttek--generator-bundle--generator` asks the API for `count` results — unique unless `unique` is false — and writes
each as the text of a list item, never as HTML. A status element, `role="status"`, says when the draw fails or is
short, an attribution element, when there is one, shows the attributions of the new results the same way as the
template, and the element is `aria-busy` during the request.

```html
<div data-controller="tilttek--generator-bundle--generator"
     data-tilttek--generator-bundle--generator-url-value="/api/generators/name.short"
     data-tilttek--generator-bundle--generator-count-value="5"
     data-tilttek--generator-bundle--generator-unique-value="true"
     data-tilttek--generator-bundle--generator-short-message-value="Fewer names than asked were found.">
    <ul data-tilttek--generator-bundle--generator-target="results"></ul>
    <p data-tilttek--generator-bundle--generator-target="attribution"></p>
    <p data-tilttek--generator-bundle--generator-target="status" role="status"></p>
    <button type="button" data-action="tilttek--generator-bundle--generator#draw">Again</button>
</div>
```
