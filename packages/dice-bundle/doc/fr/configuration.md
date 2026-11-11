<!-- phil-dev source-sha256=c967f6d27c600e814b8ddfa74f180779e9e169d2a3090be4ca92e719e487fa5b -->
# Configuration

```yaml
tilttek_dice:
    limits:
        dice: 100          # dice a pool rolls, lists or draws
        sides: 1000        # sides of a die
        explosions: 100    # explosions of a roll
        expressions: 10    # instructions of a pool
        length: 256        # characters of an input
        rerolls: 100       # rerolls of a roll, repeated, once or unique
        repetitions: 100   # repetitions of repeat()
        text: 4096         # characters of a text a sentence gives
    custom_dice:
        chromatic:
            faces:
                - { id: red, label: 'dice.red', color: '#d32f2f', shape: circle }
                - { id: star, label: 'dice.star', image: '/images/star.svg' }
    notations:
        fi: { die: n }                     # advantage: adv, disadvantage: dis by default
    appearance: null                       # a service implementing DiceAppearance
    translation_domain: tilttek_dice
    store: doctrine                        # or memory
    entity_manager: default
```

| Option | Valeur par défaut | Sens |
|---|---|---|
| `limits.*` | comme ci-dessus | Chacune vaut au moins 1 ; une expression ou un lancer qui en dépasse une est refusé |
| `custom_dice` | aucun | Les dés de l'application, par nom ; chaque face a un `id` et un `label`, et une `value`, une `color`, une `shape` ou une `image` |
| `notations` | aucune | Les lettres d'autres langues, par locale, ajoutées à l'allemand, au danois, au norvégien et au suédois intégrés |
| `appearance` | `null` | Le service qui habille les dés d'un membre ; des dés ordinaires s'il est nul |
| `translation_domain` | `tilttek_dice` | Le domaine des mots d'un lancer et des libellés des faces |
| `store` | `doctrine` | Où sont gardés le journal des lancers rejouables et les macros : `doctrine` ou `memory` |
| `entity_manager` | `default` | Le gestionnaire d'entités du journal et des macros |

Une forme est l'une de `circle`, `square`, `triangle`, `diamond`, `pentagon`, `hexagon`, `star`, `cross`, `heart`,
`crescent` et `blank`. Les dés personnalisés et les notations sont construits à la compilation du conteneur. Une face
qui ne se distingue que par sa couleur, deux faces qui partagent un identifiant ou une lettre de dé déjà prise par un
modificateur échouent alors, avec le message de la bibliothèque.

Les services ne gardent aucun état d'une requête à l'autre : ils sont sûrs dans un *worker*. Le journal et les macros
en mémoire se vident quand le noyau se réinitialise.
