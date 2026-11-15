<!-- phil-dev source-sha256=48d708581b01913faa9c29fc1a5d0e002cfc1124785d6f118fad0a90ae8359a1 -->
# tilttek/luck-tokens

**Jetons de chance, d'inspiration ou de destin qu'une table distribue et dépense, chaque mouvement dans un registre en ajout seulement.**

Un meneur de jeu distribue un jeton pour un plan astucieux ; un joueur le dépense pour relancer ; un autre passe un
bonus à un ami en difficulté ; au début de la session suivante, les points de destin remontent jusqu'à leur
rafraîchissement. Chaque table dit quels jetons elle distribue — leur nom, combien un détenteur en garde au plus,
s'ils se conservent d'une session à l'autre et comment ils se rafraîchissent, qui peut les accorder et s'ils passent
d'un détenteur à un autre — et la bibliothèque garde chaque mouvement dans le registre de la table, qui ne fait que
grandir, pour que le meneur de jeu et les joueurs vérifient chaque jeton.

Un solde ne tombe jamais sous zéro ni ne dépasse son plafond. Chaque opération porte un identifiant choisi par
l'appelant : envoyée de nouveau — un double clic, une requête reprise —, elle n'écrit rien de plus et redonne ce
qu'elle avait écrit. Deux opérations simultanées sur la même table n'écrivent pas toutes les deux après la même
entrée : la seconde relit le registre et est vérifiée de nouveau. Une fois écrits, un octroi, une dépense, un transfert
ou le début d'une session sont répartis comme un événement, pour qu'un lanceur de dés offre sa relance ou qu'une tour
de dés montre le jeton dépensé. Toute heure est écrite en UTC. Un membre qui part est effacé de chaque registre sous un
pseudonyme, les soldes des autres inchangés.

C'est une bibliothèque PHP simple. L'application garde les règles et le registre dans son propre stockage, par deux
ports, et la bibliothèque fournit un stockage en mémoire de chacun. Les jetons ne sont pas des points : ils ne se
gagnent pas par l'activité et ne s'échangent pas.

## État

| Partie | État |
|---|---|
| `LuckTokens` : octrois, dépenses, transferts et début d'une session, idempotents, vérifiés selon les règles | Implémenté et testé |
| `TokenKind`, `TableRules` : les sortes de jetons d'une table, leur plafond, leur conservation, leur rafraîchissement, leurs octrois et leurs transferts ; lus à partir de JSON | Implémenté et testé |
| `Ledger\LedgerEntry`, `Ledger\EntryType`, `Receipt`, `Balance` : le registre en ajout seulement et ce qu'une opération a écrit | Implémenté et testé |
| `Event\TokensGranted`, `TokensSpent`, `TokensTransferred`, `SessionStarted`, répartis par PSR-14 | Implémenté et testé |
| `eraseHolder()` : un membre effacé sous un pseudonyme, et `Event\HolderErased` | Implémenté et testé |
| `Contract\TokenRules`, `Contract\TokenLedger`, `Contract\HolderAccess`, avec des stockages en mémoire | Implémenté et testé |
| Exceptions sous une seule interface `LuckTokensException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Utilisation](doc/fr/usage.md) | Les sortes de jetons, octroyer, dépenser, passer, les sessions, lire le registre, et effacer un membre. |
| [Accessibilité](doc/fr/accessibility.md) | Afficher les soldes et annoncer ce qui change. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, écrire un registre, et les événements. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle, et ses exceptions. |

## Démarrage rapide

```php
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;
use Tilttek\LuckTokens\Store\InMemoryTokenRules;
use Tilttek\LuckTokens\TableRules;

$table = ResourceReference::of('table', '12');
$gm = ResourceReference::of('member', '7');
$ana = ResourceReference::of('member', '8');

$rules = new InMemoryTokenRules();
$rules->save(TableRules::fromJson($table, '{"kinds": [{"key": "inspiration", "name": "Inspiration", "cap": 1}],
    "game_masters": ["member:7"]}'));
$tokens = new LuckTokens($rules, new InMemoryTokenLedger(), $eventDispatcher);

$tokens->grant($table, 'inspiration', $ana, 1, $gm, 'op-01J2', 'A clever plan');
$tokens->spend($table, 'inspiration', $ana, 1, $ana, 'op-01J3', 'Reroll the attack');
$tokens->log($table);   // both entries, for the audit
```

## Prérequis

PHP 8.5 ou une version plus récente, `phil/resource` 0.1, `psr/clock`, `psr/event-dispatcher` et `symfony/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
