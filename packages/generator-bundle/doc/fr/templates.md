<!-- phil-dev source-sha256=0ad5c4c8380493d02d80a51bd1f2e39d1c7507594dec53302101bf50bf7aae00 -->
# Gabarits

## `tilttek_draw`

Avec `symfony/twig-bundle`, la fonction `tilttek_draw(generator, count = 1, unique = false, seed = null)` fait un tirage
dans un gabarit et donne un `DrawResult` de `tilttek/generator` :

```twig
{% set names = tilttek_draw('name.short', 5, true) %}
<ul>{% for name in names.results %}<li>{{ name }}</li>{% endfor %}</ul>

{% set inn = tilttek_draw('tavern', seed: 'today').first %}
<h2>{{ inn.name }}</h2>
<p>{{ inn.description }}</p>
```

Les résultats sont de simples textes et tableaux, échappés par les gabarits de l'application comme toute autre valeur.
La fonction est un *runtime* Twig : le générateur est construit, et son catalogue vérifié, seulement quand un gabarit
l'appelle.

## Le gabarit qui refait le tirage

`@TilttekGenerator/draw.html.twig` montre les résultats d'un tirage dans une liste, avec un bouton qui demande de
nouveaux résultats à l'API de tirage et les met en place :

```twig
{{ include('@TilttekGenerator/draw.html.twig', {
    result: tilttek_draw('name.short', 5, true),
    url: path('tilttek_generator_draw', {generator: 'name.short'}),
    again: 'Again',
    short: 'Fewer names than asked were found.',
}) }}
```

| Variable | Signification |
|---|---|
| `result` | Le premier tirage, montré avant toute requête. |
| `url` | L'adresse du générateur dans l'API de tirage, qui doit l'ouvrir à tout le monde. |
| `again` | Le texte du bouton ; `Again` par défaut. |
| `short` | Ce que dit le statut quand un tirage trouve moins de résultats que demandé. |

Un résultat structuré montre son champ `name`, ou son JSON ; une application qui montre davantage écrit son propre
gabarit avec le même contrôleur Stimulus. Un paragraphe de classe `tilttek-generator-attribution` suit les résultats
avec leurs attributions, chacune étant son attribution, ou sa source, puis sa licence entre parenthèses, séparées par
des points-virgules : `Tables by A. Author (CC-BY-4.0); A grammar`.

## Le contrôleur Stimulus

`tilttek--generator-bundle--generator` demande `count` résultats à l'API (uniques, sauf si `unique` est faux) et écrit
chacun comme le texte d'un élément de liste, jamais comme du HTML. Un élément de statut, `role="status"`, indique quand
le tirage échoue ou est incomplet, un élément d'attribution, quand il y en a un, montre les attributions des nouveaux
résultats comme le gabarit, et l'élément est `aria-busy` pendant la requête.

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
