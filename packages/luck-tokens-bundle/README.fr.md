<!-- phil-dev source-sha256=2ba82dcb49caa515178e3a9becd7a747bf607bc6929eb83fb9fd88f62507a9f8 -->
# tilttek/luck-tokens-bundle

**Des jetons de chance, d'inspiration et de destin pour les tables d'une application Symfony, chaque mouvement gardé dans la base de données.**

tilttek/luck-tokens-bundle intègre `tilttek/luck-tokens` à Symfony. Il garde les registres des tables, et leurs règles
quand l'application les modifie, dans des tables d'une connexion Doctrine DBAL, ajoutées au schéma que génère l'ORM pour
que les migrations de l'application les créent. Les règles peuvent aussi venir de la configuration ou d'un service de
l'application. Un solde se lit dans un index, jamais en additionnant le registre ; deux écrivains simultanés sur une
table se distinguent par la clé primaire, et le second relit le registre. Un verrou Symfony facultatif exécute les
opérations d'un détenteur, ou de toute une table, l'une après l'autre. Les événements atteignent le répartiteur
d'événements de l'application ; qui dépense les jetons de qui peut être décidé par les voters de sécurité ; un membre qui
part est effacé sous un pseudonyme, par `phil/privacy-bundle` ou par un écouteur de l'application.

Il s'adresse à une application Symfony dont les tables distribuent des jetons — inspiration, bennies, points de
destin — et qui veut que chacun soit octroyé, dépensé et passé une seule fois, avec une vérification que le meneur de
jeu et les joueurs peuvent lire.

Il exige DependencyInjection et Config de Symfony, Doctrine DBAL, le répartiteur d'événements PSR, `tilttek/luck-tokens`
et `phil/resource`. L'ORM, les composants Lock et Security et `phil/privacy-bundle` sont facultatifs : l'écouteur du
schéma, les verrous, l'accès par les voters et le module d'effacement ne sont enregistrés que lorsqu'ils sont installés
et, pour les verrous et les voters, demandés.

La conception suit celle des autres bundles du monodépôt : un `AbstractBundle` avec sa configuration, des services
déclarés par `LuckTokensServices`, des stockages DBAL et ceux en mémoire de la bibliothèque pour les tests, des tables
ajoutées par un écouteur du schéma.

## État

| Partie | État |
|---|---|
| Configuration `tilttek_luck_tokens` : stockage, connexion, règles, tables, horloge, taille d'un registre, accès, verrous, sorte de membre | Implémenté et testé |
| `DbalTokenLedger` et `DbalTokenRules`, avec leurs deux tables | Implémenté et testé |
| Les stockages DBAL sur SQLite, MySQL 8.4 et PostgreSQL 17, dont quatre processus simultanés | Implémenté et testé |
| Les tables dans le schéma que génère l'ORM, pour les migrations | Implémenté et testé |
| `LockedLuckTokens` : les opérations sous un verrou Symfony, par détenteur ou par table | Implémenté et testé |
| Les événements par le répartiteur de l'application | Implémenté et testé |
| `SecurityHolderAccess` et `HolderControlVoter` : l'accès demandé aux voters de sécurité | Implémenté et testé |
| L'effacement d'un membre sous un pseudonyme pour `phil/privacy-bundle` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, la migration. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `tilttek_luck_tokens`, avec sa valeur par défaut. |
| [Les registres dans la base de données](doc/fr/database.md) | Les tables, les index, les écrivains simultanés, les règles gardées là. |
| [Verrous](doc/fr/locks.md) | `LockedLuckTokens`, les portées, le stockage des verrous. |
| [Qui contrôle qui](doc/fr/holder-access.md) | Chaque détenteur les siens, les voters de sécurité, un service de l'application. |
| [Événements](doc/fr/events.md) | Écouter les événements des jetons. |
| [Effacer un membre](doc/fr/erasing-a-member.md) | Le pseudonyme, le bundle de vie privée, un écouteur de l'application. |
| [Points d'extension](doc/fr/extension-points.md) | Les services, les alias et les étiquettes qu'emploie l'application. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, sur SQLite, MySQL et PostgreSQL, son banc d'essai, et tester une application. |
| [Référence](doc/fr/reference.md) | Chaque classe du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/luck-tokens-bundle
```

```yaml
# config/packages/tilttek_luck_tokens.yaml
tilttek_luck_tokens:
    tables:
        'table:12':
            game_masters: ['member:7']
            kinds:
                - { key: inspiration, name: Inspiration, cap: 1, transferable: true }
                - { key: fate, name: Fate points, cap: 10, refresh: each-session, refresh_to: 3 }
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\LuckTokens;

final readonly class Reroll
{
    public function __construct(private LuckTokens $tokens) {}

    public function __invoke(int $memberId, string $operation): void
    {
        $member = ResourceReference::of('member', (string) $memberId);
        $this->tokens->spend(ResourceReference::of('table', '12'), 'inspiration', $member, 1, $member, $operation, 'Reroll');
    }
}
```

Ensuite, `bin/console doctrine:migrations:diff` écrit la migration de la table du registre.

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2, Doctrine DBAL 4.5.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
