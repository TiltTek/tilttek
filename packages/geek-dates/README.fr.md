<!-- phil-dev source-sha256=3512d20a83ba7919d54535ea4fe931971f18b4e2446bb99586839a767730faab -->
# tilttek/geek-dates

**Les jours de la culture geek à la bonne date chaque année — Towel Day, Jour du Pi, Jour d'Ada Lovelace —, chacun avec sa source.**

tilttek/geek-dates est un catalogue des jours que garde la culture geek : jours des sciences et des mathématiques,
jours de l'informatique, jours des lecteurs de Tolkien ou de Douglas Adams. Chaque entrée est une règle de
`phil/almanac` — `05-25`, `last friday of july`, `2nd tuesday of october`, `day 256` —, jamais une liste de dates par
année, avec ses noms en anglais et en français, l'adresse de la source contre laquelle sa règle a été vérifiée, le
pays ou la communauté qui la garde, le jour de sa dernière vérification et ses étiquettes. Le catalogue calcule les
dates d'une année, d'une période ou d'un jour — l'objet du jour —, filtrées par étiquette, par communauté ou aux
entrées vérifiées, et chaque date qu'il donne porte son entrée et sa source.

Une application ajoute ses propres entrées — le jour d'un club local, un congrès dont elle connaît la date — sous la
même forme, à partir de tableaux ou en code, sans toucher aux données fournies. `DataCheck` liste chaque problème
d'un ensemble de lignes : une règle qui ne se lit pas, une source ou une date de vérification manquante, un
identifiant présent deux fois.

Il sert à une application qui montre des dates de la culture geek : un objet du jour, un calendrier de l'Avent, des
événements saisonniers, un tirage un jour donné. C'est une bibliothèque PHP simple qui dépend de `phil/almanac`, de
`phil/url` et de `ext-mbstring`. Les données ne contiennent aucune « fête nationale » commerciale, aucune date qu'une
marque déclare à propos d'elle-même et aucune date de congrès.

## État

| Partie | État |
|---|---|
| `Entry`, `Status` : des entrées avec noms, règle, source, communauté, date de vérification, étiquettes et jours | Implémenté et testé |
| `EntryReader` : des entrées à partir de tableaux, sous la forme des données fournies et celle d'une application | Implémenté et testé |
| `Catalog`, `Filter`, `Occurrence` : les dates d'une année, d'une période ou d'un jour, filtrées, avec leur source | Implémenté et testé |
| `ShippedData`, `data/dates.php`, `data/VERSION` : 18 entrées, chacune vérifiée contre sa source | Implémenté et testé |
| `DataCheck` : chaque problème d'un ensemble de lignes | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle, et les questions du catalogue. |
| [Données](doc/fr/data.md) | Les entrées fournies et leurs sources, ce qui est exclu, l'ajout d'une entrée. |
| [Exceptions](doc/fr/exceptions.md) | Ce que signifie chaque exception, et les problèmes que `DataCheck` liste. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests et les bancs d'essai du paquet. |

## Démarrage rapide

```php
use Tilttek\GeekDates\Catalog;
use Tilttek\GeekDates\EntryReader;
use Tilttek\GeekDates\Filter;

$catalog = Catalog::shipped(new DateTimeZone('America/Toronto'), [
    new EntryReader()->read([
        'id' => 'club-anniversary',
        'names' => ['en' => 'Club anniversary', 'fr' => 'Anniversaire du club'],
        'rule' => '2nd saturday of march',
        'source' => 'https://club.example/about',
        'community' => 'our-club',
        'verified' => '2026-11-21',
    ]),
]);

foreach ($catalog->on(new DateTimeImmutable('2026-05-25')) as $date) {
    echo $date->entry->name('fr_CA'), ' — ', $date->source(), "\n";
}

$science = $catalog->year(2027, new Filter(tags: ['science']));
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring` ; `phil/almanac` 0.1 ; `phil/url` 0.1, avec `ext-intl` et `ext-uri`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE). Les
données contiennent des faits — un jour et sa règle — vérifiés contre les sources que [Données](doc/fr/data.md#sources)
énumère ; ses noms sont ceux du paquet.
