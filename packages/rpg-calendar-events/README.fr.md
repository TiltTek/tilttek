<!-- phil-dev source-sha256=fe7a3e2fcb006e87ac4e0afd3b9088c7aba4a38fde046107fca5b38a6fe7ddf2 -->
# tilttek/rpg-calendar-events

**Les événements de la communauté du jeu de rôle à la bonne date chaque année, et le jour d'un mois d'invites quotidiennes, chacun avec sa source.**

tilttek/rpg-calendar-events est un catalogue des événements annuels de la communauté du jeu de rôle : le Jour du MJ,
le mois d'invites quotidiennes de RPGaDAY, le Mois national de la conception de jeux. Chaque entrée est une règle de
`phil/almanac` — `03-04`, `08-01` pendant 31 jours, `3rd saturday of june` —, jamais une liste de dates par année,
avec ses noms en anglais et en français, l'adresse de la source contre laquelle sa règle a été vérifiée, la communauté
qui la garde, le jour de sa dernière vérification et ses étiquettes. Le catalogue calcule les dates d'une année,
d'une période ou d'un jour, filtrées par étiquette, par communauté ou aux entrées vérifiées, et chaque date qu'il
donne porte son entrée et sa source.

Une période thématique d'invites quotidiennes est une plage avec un indice : le 12 août, `promptDays()` donne le
douzième jour de 31, et l'application montre sa propre douzième invite — le paquet ne fournit aucune liste d'invites.
Une application ajoute ses propres événements — les congrès qu'elle connaît, une journée de jeu locale — sous la même
forme, sans toucher aux données fournies. `DataCheck` liste chaque problème d'un ensemble de lignes.

Il sert à une application qui transforme le calendrier du jeu de rôle en quêtes du jour, en promotions ou en rappels.
C'est une bibliothèque PHP simple qui dépend de `phil/almanac`, de `phil/url` et de `ext-mbstring`. Les données ne
contiennent aucun congrès, aucun événement dont l'organisateur fixe la date chaque année et aucune date qu'une marque
déclare à propos d'elle-même.

## État

| Partie | État |
|---|---|
| `Entry`, `Status` : des événements avec noms, règle, source, communauté, date de vérification, étiquettes, jours et invites | Implémenté et testé |
| `EntryReader` : des événements à partir de tableaux, sous la forme des données fournies et celle d'une application | Implémenté et testé |
| `Catalog`, `Filter`, `Occurrence` : les dates d'une année, d'une période ou d'un jour, filtrées, avec leur source | Implémenté et testé |
| `PromptDay` : le jour d'une période thématique d'invites quotidiennes, avec son indice et sa durée | Implémenté et testé |
| `ShippedData`, `data/events.php`, `data/VERSION` : 3 événements, chacun vérifié contre sa source | Implémenté et testé |
| `DataCheck` : chaque problème d'un ensemble de lignes | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle, les questions du catalogue et les jours d'invites. |
| [Données](doc/fr/data.md) | Les événements fournis et leurs sources, ce qui est exclu, l'ajout d'un événement. |
| [Exceptions](doc/fr/exceptions.md) | Ce que signifie chaque exception, et les problèmes que `DataCheck` liste. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests et les bancs d'essai du paquet. |

## Démarrage rapide

```php
use Tilttek\RpgCalendarEvents\Catalog;
use Tilttek\RpgCalendarEvents\EntryReader;

$catalog = Catalog::shipped(new DateTimeZone('America/Toronto'), [
    new EntryReader()->read([
        'id' => 'local-con',
        'names' => ['en' => 'Our convention', 'fr' => 'Notre convention'],
        'rule' => '3rd saturday of june',
        'days' => 2,
        'source' => 'https://convention.example/dates',
        'community' => 'montreal',
        'verified' => '2026-11-21',
    ]),
]);

foreach ($catalog->promptDays(new DateTimeImmutable('2027-08-12')) as $day) {
    echo $day->occurrence->entry->name('fr'), ': day ', $day->index, ' of ', $day->length(), "\n";
}

$events = $catalog->year(2027);
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring` ; `phil/almanac` 0.1 ; `phil/url` 0.1, avec `ext-intl` et `ext-uri`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE). Les
données contiennent des faits — un événement et sa règle — vérifiés contre les sources que
[Données](doc/fr/data.md#sources) énumère ; ses noms sont ceux du paquet.
