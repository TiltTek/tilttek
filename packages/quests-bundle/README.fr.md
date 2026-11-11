<!-- phil-dev source-sha256=1013ee465e304bb044fc23c97d13b418622b6727e63f69fffc8dcc9d884eff89 -->
# tilttek/quests-bundle

**Des quêtes et des défis pour une application Symfony, tirés du journal de l'activité de ses membres.**

tilttek/quests-bundle intègre `tilttek/quests` à Symfony. Les quêtes, avec leurs étapes, leurs genres, leurs dates,
leurs chaînes et leurs récompenses, sont écrites dans la configuration et vérifiées à la compilation du conteneur. Elles
peuvent aussi être gardées en base de données et modifiées par l'application. Une quête qui revient à des dates
calculées, comme une semaine à partir de Pâques ou les jours autour d'un solstice, est lue par `phil/almanac`. La
progression, les acceptations des membres, le journal des quêtes accomplies et les duels sont gardés dans Doctrine DBAL.
Ils occupent cinq tables que le bundle ajoute au schéma généré par l'ORM : les migrations de l'application les créent
donc. La projection qui transforme l'activité des membres en progression porte l'étiquette de
`tilttek/activity-bundle`. Celui-ci la tient à jour à chaque geste, la rattrape et la reconstruit. Une quête accomplie
devient un événement du répartiteur de l'application, avec ses récompenses et les membres qui les reçoivent ; il en va
de même d'un duel gagné et d'un duel expiré. Des fonctions Twig et deux gabarits affichent la liste et le détail des
quêtes. Des commandes listent les quêtes, affichent celles d'un membre et font expirer les duels. Enfin, les quêtes
d'un membre sont exportées et effacées avec son compte par `phil/privacy-bundle`.

Il s'adresse à une application Symfony qui donne à ses membres des objectifs à atteindre, seuls, en équipe, en
communauté ou les uns contre les autres. La bibliothèque seule convient à une application sans Symfony.

Il exige `tilttek/quests`, Doctrine DBAL, le répartiteur d'événements PSR ainsi que la configuration et le conteneur de
Symfony. `phil/almanac`, l'ORM, la console, Twig, le traducteur et `phil/privacy-bundle` sont facultatifs : le bundle
n'enregistre ce qui les utilise que s'ils sont installés.

La conception suit celle de la bibliothèque : les dépôts implémentent ses quatre contrats, `DbalCatalog` son catalogue
modifiable, `AlmanacScheduleReader` ses lecteurs de calendrier. `QuestsServices` relie les services,
`DispatchingObserver` transforme en événements ce que la bibliothèque annonce, et `QuestsExtension` en est le côté Twig.

## État

| Partie | État |
|---|---|
| Configuration des quêtes, des dépôts, du catalogue, du fuseau horaire et des duels, vérifiée à la compilation | Implémentée et testée |
| `DbalContributionStore`, `DbalEnrolmentStore`, `DbalCompletionStore`, `DbalDuelStore` et les tables, avec l'écouteur du schéma | Implémentés et testés |
| `DbalCatalog` : quêtes gardées en base de données sous forme de définitions JSON, chaînes vérifiées à l'enregistrement et au retrait | Implémenté et testé |
| `AlmanacSchedule` et `AlmanacScheduleReader` : quêtes qui reviennent aux dates calculées par `phil/almanac` | Implémentés et testés |
| La projection étiquetée pour `tilttek/activity-bundle` | Implémentée et testée |
| Événements `QuestCompleted`, `DuelWon` et `DuelExpired` | Implémentés et testés |
| Fonctions Twig et gabarits de la liste et du détail, en anglais et en français | Implémentés et testés |
| Commandes `tilttek:quests:list`, `tilttek:quests:show` et `tilttek:quests:expire` | Implémentées et testées |
| Exportation et effacement d'un membre pour `phil/privacy-bundle` | Implémentés et testés |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, les tables et la projection. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple, et les quêtes qui reviennent. |
| [Base de données](doc/fr/database.md) | Les cinq tables, leurs clés et le catalogue gardé en base de données. |
| [Événements](doc/fr/events.md) | Les événements que le bundle répartit et la façon de donner les récompenses d'une quête. |
| [Gabarits](doc/fr/templates.md) | Les fonctions Twig, la liste et le détail, et leurs traductions. |
| [Commandes](doc/fr/commands.md) | Lister les quêtes, afficher celles d'un membre, faire expirer les duels. |
| [Vie privée](doc/fr/privacy.md) | Ce que contient l'exportation d'un membre et ce que son effacement supprime. |
| [Points d'extension](doc/fr/extension-points.md) | Remplacer un dépôt ou le catalogue, un autre calendrier, l'observateur. |
| [Exceptions](doc/fr/exceptions.md) | Ce que le bundle lance. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et les tests d'une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
Tilttek\QuestsBundle\TilttekQuestsBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_quests.yaml
tilttek_quests:
    timezone: America/Toronto
    quests:
        first-steps:
            ordered: true
            steps: [{ code: play, verbs: ['game.played'], count: 3 }, { code: write, verbs: ['sheet.published'] }]
            rewards: [{ kind: points, code: xp, amount: 50 }]
        easter-hunt:
            recurs: { from: 'easter - 2 days', until: 'easter + 1 day' }
            steps: [{ verbs: ['egg.found'], count: 10 }]
```

```php
#[AsEventListener]
public function __invoke(QuestCompleted $event): void
{
    foreach ($event->beneficiaries as $member) {
        foreach ($event->rewards() as $reward) {
            // give the reward through the packages the application has
        }
    }
}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 et Doctrine DBAL 4.5.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Consultez [LICENSE](LICENSE).
