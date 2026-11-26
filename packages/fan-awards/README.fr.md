<!-- phil-dev source-sha256=b7ebc55fe6e361e4cc297ff742b5b0d283bdd9459e09056bea60754a70cf2b36 -->
# tilttek/fan-awards

**Prix des fans votés en tours : candidatures ouvertes, demi-finale ouverte aux ajouts, finale classée avec « aucun prix », dépouillés par `phil/poll`.**

tilttek/fan-awards gère les prix qu'une communauté remet chaque année — meilleur roman, meilleur jeu, meilleure
diffusion — de la première candidature à la cérémonie. Une édition compte ses catégories, chacune avec ses propres
règles : combien de candidats contient un bulletin de mise en candidature, combien passent à la demi-finale et à la
finale, et la méthode de dépouillement de la finale. Les organisateurs peuvent proposer des candidats ; les
votants proposent librement, en ajoutant une œuvre que la liste n'a pas, que les règles d'admissibilité de l'application
vérifient — publiée l'année du prix, du genre de la catégorie. Les plus proposés passent à une demi-finale, où un votant
peut encore ajouter un candidat, puis à une finale classée qui offre « aucun prix » à côté des finalistes, afin qu'une
liste poussée par un groupe puisse être refusée.

Chaque dépouillement est fait par `phil/poll`, rien n'est recompté ici : les candidatures et la demi-finale par le
décompte des choix, la finale par la méthode classée de la catégorie — le second tour instantané, le décompte de Borda
ou la méthode de Schulze. Une fois la finale close, les organisateurs lisent les résultats ; une fois les gagnants
annoncés, tout le monde les lit avec le décompte détaillé de chaque phase. Qui peut voter est une règle de
l'application, jamais un paiement ni un achat. Les votants sont des références que l'application donne ; en effacer un
garde ses bulletins, anonymes, dans les décomptes.

Elle convient à un site communautaire, à un congrès ou à un fan-club. C'est une bibliothèque PHP simple dont les
dépendances sont `phil/poll`, `phil/resource` et `psr/clock`.

## État

| Partie | État |
|---|---|
| `Edition`, `Category`, `Nominee`, `Phase` : les éditions, leurs catégories et leurs règles, les candidats, les phases | Implémenté et testé |
| `FanAwards` : créer, proposer, voter avec ajouts, avancer et dépouiller, prévisualiser, publier, effacer | Implémenté et testé |
| `PhaseCount`, `Results` : le décompte de chaque phase, les gagnants, « aucun prix » | Implémenté et testé |
| `Contract\NomineeEligibility`, `Contract\VoterEligibility`, avec `Eligibility\AnyNominee` et `Eligibility\AnyVoter` | Implémenté et testé |
| `Contract\AwardStore`, avec `Store\InMemoryAwardStore` | Implémenté et testé |
| Un bundle Symfony : dépôt Doctrine, formulaires de bulletin, phases planifiées, traductions | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Éditions et phases](doc/fr/editions.md) | Catégories, candidats, phases, proposer, voter, ajouts, admissibilité. |
| [Dépouillement et résultats](doc/fr/counting.md) | Présélections et égalités, la finale, « aucun prix », résultats scellés et publiés, effacement. |
| [Points d'extension](doc/fr/extension-points.md) | Le magasin, les règles d'admissibilité, les méthodes de dépouillement et l'horloge. |
| [Exceptions](doc/fr/exceptions.md) | `FanAwardsException` et ses exceptions, avec les refus et leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, et les tests du code qui gère des prix. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/fan-awards
```

```php
use Tilttek\FanAwards\Category;
use Tilttek\FanAwards\FanAwards;
use Tilttek\FanAwards\Nominee;
use Tilttek\FanAwards\Store\InMemoryAwardStore;

$awards = new FanAwards(new InMemoryAwardStore(), $clock);

$awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel')]);
$awards->propose('2027', 'novel', new Nominee('the-long-road', 'The Long Road'));

$awards->advance('2027');                     // nominations open
$awards->vote('2027', 'novel', $voter, ['the-long-road', new Nominee('night-ferry', 'Night Ferry')]);

$awards->advance('2027');                     // the semifinal, on the nominations' shortlist
$awards->advance('2027');                     // the final, "no award" among its options
$awards->vote('2027', 'novel', $voter, ['night-ferry', 'no-award']);

$awards->advance('2027');                     // counted: preview() for the organisers
$awards->advance('2027');                     // announced
$awards->results('2027', 'novel')->winners;   // and every phase's count
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
