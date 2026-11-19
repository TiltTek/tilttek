<!-- phil-dev source-sha256=9e8397336015299957b55bd02f47c0828ba949c6d92beb247dc72cf4fb23d76d -->
# Configuration

```yaml
# config/packages/tilttek_advent_calendar.yaml
tilttek_advent_calendar:
    store: doctrine               # or "memory", for tests
    connection: default           # the DBAL connection of the openings
    entity_manager: default       # the entity manager of the calendars the site edits
    member_resolver: App\Advent\CurrentMember
    layout: base.html.twig        # the template the screen extends; it fills its block "body"
    kit_subdirectory: ~           # where the kit's recipes are; the Toolkit's subdirectory when null
    celebration:
        enabled: true             # whether the screen plays the kit's Celebration for a first opening
        effect: confetti          # or "fireworks"
    calendars:
        advent-2026:
            title: Advent 2026
            timezone: America/Toronto
            first_date: '2026-12-01'
            closing_date: '2027-01-06'  # the date of the last box when left out
            policy: until_closing       # "same_day" or "catch_up"
            catch_up_days: 0            # 1 to 31 for "catch_up"
            published: true
            surprises:
                - { kind: points, quantity: 10 }            # the ledger "points" when the reference is left out
                - { kind: badge, reference: advent-week }
                - { kind: content, reference: 'recipe:gingerbread' }
                - { kind: code, reference: gift-codes }
    privacy:
        member_kind: member       # the kind of the reference a data subject of phil/privacy-bundle stands for
```

| Option | Valeur par défaut | Sens |
|---|---|---|
| `store` | `doctrine` | Où sont gardées les ouvertures : Doctrine DBAL, ou la mémoire pour les tests. |
| `connection` | `default` | La connexion DBAL des ouvertures, et de l'écouteur du schéma. |
| `entity_manager` | `default` | Le gestionnaire d'entités des calendriers que le site modifie, lu quand l'ORM est installé. |
| `member_resolver` | aucune | Le service qui implémente `MemberResolver` ; sans lui, chaque spectateur est un visiteur. |
| `layout` | `base.html.twig` | Le gabarit que l'écran étend. |
| `kit_subdirectory` | aucune | Le sous-répertoire des recettes du kit sous le répertoire des composants ; celui du Toolkit quand elle est nulle. |
| `celebration.enabled` | `false` | Si l'écran joue la `Celebration` du kit pour une case ouverte pour la première fois. |
| `celebration.effect` | `confetti` | Ce que dessine la célébration là où le mouvement n'est pas réduit : `confetti` ou `fireworks`. |
| `calendars` | aucun | Les calendriers par identifiant ; chacun est vérifié par la bibliothèque à la construction du conteneur. |
| `privacy.member_kind` | `member` | Le genre de la référence d'un membre, pour `phil/privacy-bundle`. |

L'identifiant d'un calendrier fait de 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, et commence par une lettre
ou un chiffre ; c'est la clé de ses ouvertures et de son adresse, de sorte qu'il ne change plus une fois que des
membres ouvrent des cases. Un calendrier de la configuration masque un calendrier de la base de données de même
identifiant. Un calendrier qui ne tient pas ensemble — un fuseau horaire inconnu, aucune surprise ou plus de 92, une
fermeture avant la dernière case — empêche la construction du conteneur, avec la raison de la bibliothèque.
