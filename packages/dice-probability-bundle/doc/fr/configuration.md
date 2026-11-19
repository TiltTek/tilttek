<!-- phil-dev source-sha256=4af52b76cc0eb8021303703eac193e59489073658bbbbd284d36fc0bf5eeee51 -->
# Configuration

```yaml
# config/packages/tilttek_dice_probability.yaml
tilttek_dice_probability:
    explosion_depth: 10
    work_limit: 100000
    samples: 5000
    seed: 0
    roll_limit: 50000
    max_dice: 50
    max_outcomes: 500
    group_work_limit: 2000000
    rounding: floor
    kit_subdirectory: ~
    rate_limit:
        enabled: true
        limit: 60
        interval: '1 minute'
        cache_pool: cache.app
        limiter: ~
```

| Clé | Par défaut | Ce qu'elle règle |
|---|---|---|
| `explosion_depth` | `10` | Combien d'explosions par dé un calcul exact suit ; la probabilité d'aller plus loin est indiquée |
| `work_limit` | `100000` | Combien de travail un calcul exact fait au plus avant qu'une estimation prenne le relais |
| `samples` | `5000` | De combien de jets une estimation est tirée |
| `seed` | `0` | Le germe de ces jets : le même germe donne la même estimation |
| `roll_limit` | `50000` | Combien de dés une estimation lance au plus sur tous ses échantillons ; au-delà, l'expression n'a pas de probabilités |
| `max_dice` | `50` | Combien de dés une expression lance au plus pour ses probabilités, sous la limite d'un jet de `tilttek_dice` |
| `max_outcomes` | `500` | Combien de valeurs une distribution a au plus ; au-delà, elle est refusée au lieu d'être dessinée |
| `group_work_limit` | `2000000` | Combien d'étapes la recherche des groupes d'une estimation, `6d6g10`, fait au plus sur tous ses échantillons ; au-delà, l'expression est refusée avant d'être lancée |
| `rounding` | `floor` | Comment une division est arrondie quand l'expression ne le dit pas : `floor`, `ceil`, `round`, `towards_zero` ou `away_from_zero` |
| `kit_subdirectory` | `null` | Où sont les recettes du kit UX, sous le répertoire des composants ; avec null, on prend le sous-répertoire de la configuration `ux_toolkit` |
| `rate_limit.enabled` | `true` | Si le Live Component compte ses calculs contre une limite de débit par client |
| `rate_limit.limit` | `60` | Les calculs d'un client dans l'intervalle |
| `rate_limit.interval` | `'1 minute'` | La fenêtre glissante sur laquelle la limite compte |
| `rate_limit.cache_pool` | `cache.app` | Le pool de cache où les comptes sont gardés |
| `rate_limit.limiter` | `null` | L'identifiant de service d'une fabrique de limiteurs de débit de l'application, au lieu de la fenêtre glissante que construit le bundle |

Chaque borne vaut au moins 1. Une page calcule à chaque requête, et n'importe qui peut écrire une expression courte qui
demande beaucoup de travail : `100d6!` ou `10d1000` tiennent en sept caractères. Les bornes gardent chaque requête sous
la seconde aux valeurs par défaut : la limite de travail et la limite de dés bornent le calcul et l'estimation,
`max_dice` refuse les grands ensembles de dés avant tout calcul, `group_work_limit` refuse les groupes trop longs à
chercher, et `max_outcomes` refuse un tableau de milliers de lignes avant qu'il soit dessiné. Un refus est une exception
(le `LimitExceeded` de `tilttek/dice`, le `CannotCompute` de `tilttek/dice-probability`, ou le `GroupsTooCostly` et le
`TooManyOutcomes` du bundle) que le composant dit dans son alerte. Relevez les bornes pour une page que seules des
personnes de confiance utilisent ; baissez-les pour une page plus fréquentée.

## La recherche des groupes

Un ensemble qui forme des groupes, `6d6g10`, compte le plus de groupes de dés qui atteignent chacun une somme. La
bibliothèque l'estime toujours, et chaque échantillon cherche les groupes de ses dés : de façon exhaustive jusqu'à
12 dés, en environ `n × 2ⁿ` étapes pour `n` dés, et de façon gloutonne au-delà, en environ `n²`. `12d6g12` prendrait des
dizaines de secondes sur 5 000 échantillons. Le bundle pèse cette recherche avant que la bibliothèque lance quoi que ce
soit, par `GroupSearch` :

- un ensemble d'un nombre écrit, `8d6g8`, par ce nombre ;
- un ensemble d'un nombre calculé, `(1d4)d6g6`, ou de dés pris d'autres instructions, par le pire nombre jusqu'à
  `max_dice` ;
- un ensemble qui explose, par son nombre et autant de dés de plus que les explosions d'un jet de `tilttek_dice`
  peuvent en ajouter ;
- ce que `repeat()` répète, une fois pour chaque fois, et chaque branche d'une condition.

Les étapes d'un échantillon, multipliées par `samples`, doivent rester dans `group_work_limit`. Aux valeurs par défaut,
les ensembles d'au plus 6 dés passent, en environ un tiers de seconde ; `7d6g7`, `12d6g12` ou `10d10g10` sont refusés
aussitôt, avec la raison `refusal.groups` des traductions. Avec moins d'échantillons, de plus grands ensembles passent.

## La limite de débit du composant

Le Live Component recalcule chaque fois que le navigateur renvoie les expressions, à chaque pause dans la saisie.
Chacune de ces requêtes compte un calcul contre la limite du client : 60 par minute par défaut, dans une fenêtre
glissante de `symfony/rate-limiter`. Le premier rendu, dessiné par la page elle-même, n'est pas compté. Au-delà de la
limite, le composant ne calcule rien et dit pourquoi dans son alerte, avec la raison `refusal.throttled`.

Un client est son adresse IPv4, ou son adresse IPv6 avec le reste de son /64, puisqu'on donne d'ordinaire un /64 entier
à un client ; une adresse IPv4 transposée en IPv6 compte comme l'adresse IPv4. Derrière un mandataire, configurez les
mandataires de confiance de Symfony pour que l'adresse comptée soit celle du client. La clé de la limite est une
empreinte de ce réseau, calculée avec `kernel.secret`, jamais l'adresse. Une défaillance du limiteur ou de son stockage
refuse le calcul.

Les comptes sont gardés dans `cache_pool` : sur plusieurs serveurs, nommez un pool qu'ils partagent (Redis, Memcached,
une base de données), sinon chaque serveur compte un client une fois. `limiter` nomme plutôt une fabrique de
l'application, comme une de `framework.rate_limiter`, dont l'identifiant de service est `limiter.<name>`.

Avec `symfony/ux-live-component` installé, la limite de débit exige `symfony/rate-limiter` : le conteneur refuse de
compiler sans lui, sauf si la limite de débit est désactivée :

```yaml
tilttek_dice_probability:
    rate_limit: false
```

Les gabarits dessinent avec les recettes du kit UX, que le Symfony UX Toolkit installe dans l'application sous le
sous-répertoire de sa configuration `ux_toolkit` : avec `ui`, la carte est `ui:Card`. Le bundle lit ce sous-répertoire à
la compilation du conteneur. Dans un environnement où le Toolkit n'est pas activé, comme l'est souvent la production,
répétez-le dans `kit_subdirectory`.

Les dés personnalisés, les limites et les notations sont ceux de `tilttek_dice` : une expression est lue et vérifiée
comme pour un jet.

Les services ne gardent aucun état d'une requête à l'autre : ils sont sûrs dans un *worker*.
