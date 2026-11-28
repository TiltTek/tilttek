<!-- phil-dev source-sha256=7a8b9ed4d1b2cb753f87da562e7a226ff5e8727e6d9974c7935109d2b90989b4 -->
# Installation

```sh
composer require tilttek/table-rules
```

Le paquet exige PHP 8.5 ou une version plus récente, `psr/clock`, dont l'horloge donne l'heure au service, et
`tilttek/rpg-systems`, dont les systèmes de jeu donnent les variantes de règles qu'une version peut choisir.

Une application donne à `TableRules` :

- un `Contract\RulebookStore` qui garde dans sa base de données le livre de règles de chaque table et chaque version
  publiée ;
- une `ClockInterface` PSR-20, comme la `NativeClock` de `symfony/clock` ;
- un `Contract\TableRulesObserver`, pour envoyer ses messages, ou rien ;
- un `Contract\VariantCatalogue` des variantes qu'offrent ses systèmes de jeu — `Catalogue\SystemVariantCatalogue` lit
  celles de ses systèmes de `tilttek/rpg-systems` —, ou rien, pour prendre tout identifiant comme variante ;
- un `Diff\RulebookDiffer`, ou celui par défaut.

Le service et le comparateur ne gardent aucun état entre les appels : une seule instance de chacun sert toutes les
requêtes d'un *worker* de longue durée.

`Store\InMemoryRulebookStore` garde les livres de règles en mémoire, pour les tests.
