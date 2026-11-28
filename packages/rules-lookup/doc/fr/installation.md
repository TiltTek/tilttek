<!-- phil-dev source-sha256=4fc5d15b3f133741c0887750bbd08b2194c1bfa286d081e89255a81375b90173 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `mbstring`.
- `symfony/polyfill-intl-normalizer`, qui normalise le texte que la recherche plie quand `intl` manque. L'extension
  `intl` est suggérée : elle donne les mêmes mots, environ dix fois plus vite sur un long texte accentué.
- `tilttek/rpg-systems`, qui donne les systèmes, les provenances et l'attribution.

## Installer

```sh
composer require tilttek/rules-lookup
```

## Câbler la bibliothèque

La bibliothèque n'a aucun service à configurer. L'application construit son dépôt une fois — au démarrage à chaud, ou
quand son cache est reconstruit — et le donne à `RulesLookup` :

```php
$rules = [];

foreach ($systems->all() as $system) {
    array_push($rules, ...new RulesFromSystem()->rules($system));
}

$lookup = new RulesLookup(new InMemoryRuleStore(...$rules));
```

`InMemoryRuleStore` ne change plus une fois construit, et `RulesLookup` ne garde rien entre deux appels : une seule
instance sert chaque requête, dans un *worker* comme dans une requête. La construction du dépôt indexe chaque mot de
chaque règle ; pour 5 000 règles en deux langues, elle prend environ 0,25 s ([Tests](testing.md#bancs-dessai)), si bien
que l'application le construit une fois plutôt qu'à chaque requête.
