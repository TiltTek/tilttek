<!-- phil-dev source-sha256=4a48123d7d7fd712bc5630d3ec6b10d98f91b27346a0138642c59b224594b5e1 -->
# tilttek/rules-lookup

**Cherchez dans les règles d'un système de jeu, SRD compris, dans la langue du lecteur : chaque résultat montre la
source et la licence dont il vient.**

tilttek/rules-lookup trouve la règle que cherche un joueur ou une meneuse de jeu : les mots qu'ils tapent, dans leur
langue, restreints à un système, à une version et à une catégorie. Une règle a un identifiant — son système, la version
de ce système et sa clé, `srd-5.2@5.2.1:condition.blinded` —, des titres et des textes par locale, une catégorie, les
règles auxquelles elle renvoie, et la source dont elle vient, avec sa licence et l'attribution que cette licence
demande. La recherche ignore la casse et les accents — `aveugle` trouve `Aveuglé` —, normalisés par la normalisation Unicode
(NFKD, marques sans chasse retirées, minuscules) ; elle lit le texte que voit un lecteur de la locale, le dernier mot tapé tient lieu des mots qu'il commence, et
un mot d'un titre compte plus qu'un mot d'un texte. Un résultat porte toujours la mention de sa source, et une page de
résultats les mentions de chaque source qu'elle montre, en texte ou en liste HTML échappée.

Les règles viennent des systèmes de `tilttek/rpg-systems` — chaque terme, attribut et jet nommé est une règle, quels que
soient le paquet de données ou le document dont il a été lu — ou sont construites en code. Elles sont gardées et
cherchées derrière un seul port : le dépôt en mémoire, qui indexe une fois les mots normalisés et ne change plus
ensuite, sert une application qui lit ses règles au démarrage à chaud et les tests ; un dépôt dans une base de données
ou un moteur de recherche est un adaptateur que l'application écrit. Quand les règles d'un système changent,
l'application envoie `ReindexRules`, un message qui porte seulement le système et sa version.

Il s'adresse à une application qui répond à des questions de règles — un outil de campagne, un écran de meneuse de jeu,
un robot à la table — et qui doit montrer d'où vient chaque règle. C'est une bibliothèque PHP simple dont les
dépendances sont `tilttek/rpg-systems`, pour les systèmes, les provenances et l'attribution,
`symfony/polyfill-intl-normalizer`, qui remplace `intl` quand l'extension manque, et l'extension `mbstring`.

## État

| Partie | État |
|---|---|
| `Rule`, `RuleId` : des règles avec leur système, leur version, leur clé, leurs titres et textes par locale, leur catégorie, leurs renvois et leur source | Implémenté et testé |
| `RuleQuery` : des mots normalisés sans casse ni accents, la locale, le système, la version, la catégorie et la page, avec leurs bornes | Implémenté et testé |
| `Contract\RuleStore`, `Store\InMemoryRuleStore` : le port du dépôt, et le dépôt en mémoire avec son calcul de score | Implémenté et testé |
| `RulesLookup`, `SearchResults`, `RuleHit` : la recherche, une règle par son identifiant, les renvois dans les deux sens, l'attribution de chaque résultat | Implémenté et testé |
| `RulesFromSystem` : les règles d'un système de `tilttek/rpg-systems` | Implémenté et testé |
| `Message\ReindexRules` : le message d'un système dont les règles ont changé | Implémenté et testé |
| Un dépôt sur Doctrine DBAL ou sur un moteur de recherche | Non commencé : un adaptateur de l'application, ou d'un futur bundle |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Règles](doc/fr/rules.md) | Les règles, leurs identifiants, leurs sources et leurs renvois, et les règles d'un système. |
| [Rechercher](doc/fr/searching.md) | Les requêtes, la normalisation, le score, les pages, et l'attribution des résultats. |
| [Dépôts](doc/fr/stores.md) | Le port du dépôt, le dépôt en mémoire, et l'écriture d'un dépôt à vous. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `RulesLookupException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\RpgSystems\Package\DataPackageReader;
use Tilttek\RulesLookup\RuleQuery;
use Tilttek\RulesLookup\RulesFromSystem;
use Tilttek\RulesLookup\RulesLookup;
use Tilttek\RulesLookup\Store\InMemoryRuleStore;

$system = new DataPackageReader()->load('data/open-rules');
$lookup = new RulesLookup(new InMemoryRuleStore(...new RulesFromSystem()->rules($system)));

$results = $lookup->search(new RuleQuery('aveugle', 'fr_CA', category: 'condition'));

foreach ($results->hits as $hit) {
    echo $hit->title, ': ', $hit->text, ' — ', $hit->attribution, "\n";
}

echo $results->attributionHtml();
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `mbstring`. L'extension `intl` est suggérée : la normalisation est la même sans elle, par
`symfony/polyfill-intl-normalizer`, seulement plus lente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE). La licence
couvre le code de la bibliothèque ; une règle garde la licence que sa source énonce.
