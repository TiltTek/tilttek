<!-- phil-dev source-sha256=881cb1f9da518630fbfd1737de94135812e2798c759a1c6c5fc8c1a6e6e3a860 -->
# Installation

```sh
composer require tilttek/downtime
```

Le paquet exige PHP 8.5 ou une version plus récente, avec `ext-mbstring`, qui compte les caractères des textes. Il
ne requiert aucun autre paquet.

Une application donne à `Downtime` :

- un `Contract\EntryStore` qui conserve les entrées dans sa base de données ;
- un `Contract\RuleBook` : `Rules\InMemoryRuleBook` sur les `SystemRules` que l'application déclare une fois, ou un
  livre de règles à elle ;
- un `Contract\Roster` qui dit qui mène chaque campagne et qui joue chaque personnage ;
- un `Contract\Roller` qui lance les expressions de dés de ses systèmes, ou rien, pour enregistrer les jets faits à la
  table ;
- un `Contract\DowntimeObserver`, pour envoyer ses messages, ou rien.

Le service ne garde aucun état entre les appels, et les règles sont de simples données : une seule instance sert donc
toutes les requêtes d'un *worker* de longue durée. `Store\InMemoryEntryStore` garde les entrées en mémoire, pour les
tests.
