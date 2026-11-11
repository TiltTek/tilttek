<!-- phil-dev source-sha256=fec04f2486cf4a3aec894b9dd7778f97abc9bdb569832ba01c67dcb8ad9f34a1 -->
# tilttek/badges-bundle

**Les badges des membres d'une application Symfony, gagnés par leur activité, montrés à leur choix et délivrés comme
Open Badges.**

tilttek/badges-bundle intègre `tilttek/badges` à Symfony. Il construit les badges et leurs critères à partir de la
configuration, vérifiée à la compilation du conteneur. Il garde les contributions de l'activité, les attributions, les
profils des membres et leurs consentements dans quatre tables sur une connexion Doctrine DBAL. Ces tables sont ajoutées
au schéma que génère l'ORM, pour que les migrations de l'application les créent. Il enregistre la projection qui
attribue les badges, que `tilttek/activity-bundle` tient à jour à mesure que le journal s'écrit, fait rattraper et
reconstruit. Il répartit un événement quand un badge est gagné ou retiré. `Badges` donne à l'application les
attributions, la progression, les attributions manuelles, la vitrine, le titre, le partage et les consentements. Des
fonctions Twig et deux gabarits affichent la vitrine et la progression. Trois commandes attribuent, retirent et
énumèrent. Le badge d'un membre est délivré comme une attestation Open Badges 3.0 par `phil/open-badges-bundle`. Un
module répond aux demandes d'export et d'effacement de `phil/privacy-bundle` pour les badges d'un membre.

Il s'adresse à une application Symfony qui reconnaît par des badges ce que font ses membres, laisse chaque membre
décider de ce qui est public, et leur donne des attestations qu'ils peuvent emporter ailleurs.

Il exige DependencyInjection et Config de Symfony, Doctrine DBAL, le répartiteur d'événements PSR, `tilttek/badges` et
`phil/resource`. `tilttek/activity-bundle` est ce qui alimente les critères ; sans lui, les badges ne sont attribués
qu'à la main. L'ORM, la Console, Twig, les contrats de Translation, le composant Uid, `phil/open-badges-bundle` et
`phil/privacy-bundle` sont facultatifs : l'écouteur du schéma, les commandes, les fonctions Twig, les attestations et
le module de vie privée ne sont enregistrés que s'ils sont installés.

La conception suit celle des autres bundles du monodépôt : un `AbstractBundle` avec sa configuration, des services
déclarés par `BadgesServices`, des stockages DBAL et des stockages en mémoire pour les tests, des tables ajoutées par un
écouteur du schéma.

## État

| Partie | État |
|---|---|
| Configuration `tilttek_badges` : stockage, connexion, fuseau horaire, badges, projection, taille de la vitrine, domaine de traduction, Open Badges, sorte de membre | Implémenté et testé |
| `DbalProgressStore`, `DbalAwardStore`, `DbalProfileStore`, `DbalGrantStore`, avec leurs quatre tables | Implémenté et testé |
| Les tables dans le schéma que génère l'ORM, pour les migrations | Implémenté et testé |
| La projection étiquetée pour `tilttek/activity-bundle`, et les événements `BadgeAwarded` et `BadgeRevoked` | Implémenté et testé |
| Fonctions Twig `tilttek_badges_showcase`, `tilttek_badges_overview`, `tilttek_badges_domain`, gabarits `showcase` et `overview` | Implémenté et testé |
| Commandes `tilttek:badges:award`, `tilttek:badges:revoke`, `tilttek:badges:list` | Implémenté et testé |
| `BadgeCredentials` : le badge d'un membre délivré comme une attestation Open Badges 3.0 de `phil/open-badges`, sécurisée selon la configuration de `phil/open-badges-bundle` | Implémenté et testé |
| Export et effacement d'un membre pour `phil/privacy-bundle` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, la migration. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `tilttek_badges`, avec sa valeur par défaut, et les critères des badges. |
| [Les badges dans la base de données](doc/fr/database.md) | Les quatre tables, l'insertion idempotente selon la plateforme, les dates. |
| [Attribuer à partir de l'activité](doc/fr/awarding.md) | La projection avec `tilttek/activity-bundle`, les événements, les reconstructions, les mesures. |
| [Afficher les badges](doc/fr/showing.md) | `Badges` dans un contrôleur, les fonctions et les gabarits Twig, la vitrine, le titre, les consentements. |
| [Commandes](doc/fr/commands.md) | `tilttek:badges:award`, `tilttek:badges:revoke` et `tilttek:badges:list`. |
| [Open Badges](doc/fr/open-badges.md) | Délivrer le badge d'un membre comme une attestation par `phil/open-badges-bundle`. |
| [Vie privée](doc/fr/privacy.md) | L'export et l'effacement des badges d'un membre. |
| [Points d'extension](doc/fr/extension-points.md) | Les services, les alias, les étiquettes et les événements qu'utilise l'application. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lève le bundle. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui attribue des badges. |
| [Référence](doc/fr/reference.md) | Chaque classe du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/activity-bundle tilttek/badges-bundle
```

```yaml
# config/packages/tilttek_badges.yaml
tilttek_badges:
    timezone: America/Toronto
    badges:
        commenter.bronze:
            name: badges.commenter.bronze.name
            tier: { series: commenter, rank: 1 }
            criterion: { count: { verbs: ['news.commented'], times: 10 } }
        regular:
            name: badges.regular.name
            criterion: { streak: { verbs: ['site.visited'], days: 7 } }
        organiser: { name: badges.organiser.name, criterion: { manual: true } }
```

```twig
{{ include('@TilttekBadges/showcase.html.twig', {showcase: tilttek_badges_showcase('member:' ~ member.id)}) }}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
