<!-- phil-dev source-sha256=f9d56cdfcb65bd19d05e83ecebd22fee795c268799a98e4613ea0d87c5a70a9b -->
# Le formulaire

```twig
{{ accessible_dice(path('table_roll', {table: table.id}), token: {name: '_token', value: csrf_token('roll')}, result: result, name: app.user.displayName) }}
```

`accessible_dice(action, field, token, result, name, politeness, id, locale)` dessine le formulaire :

| Argument | Valeur par défaut | Ce qu'il règle |
|---|---|---|
| `action` | | L'endroit où le formulaire est envoyé, par `POST` |
| `field` | `dice` | Le nom sous lequel le choix est envoyé |
| `token` | `null` | Un champ caché que l'application vérifie, tel que son jeton CSRF : `{name, value}` |
| `result` | `null` | Le jet dont le formulaire a été répondu, un `PoolResult`, annoncé au chargement de la page |
| `name` | `null` | Le nom du lanceur, dit avant ce jet |
| `politeness` | la configuration | `polite` ou `assertive` |
| `id` | `tilttek-accessible-dice` | Ce par quoi commencent les identifiants du formulaire : distinct pour chaque formulaire d'une page |
| `locale` | celle de la requête | La langue des mots et des libellés |

## Le choix envoyé

Le formulaire envoie le choix, pas une expression : `dice[die]` — `d6`, ou le nom d'un dé personnalisé —,
`dice[count]` et `dice[modifier]`. Le serveur construit l'expression à partir de lui, de sorte que le formulaire lance
de la même façon avec ou sans son script, et que rien d'autre ne peut être lancé par lui :

```php
use Tilttek\AccessibleDice\Application\AccessibleDice;
use Tilttek\AccessibleDice\Application\Exception\InvalidChoice;
use Tilttek\DiceBundle\Application\DiceRolls;

public function roll(Request $request, AccessibleDice $dice, DiceRolls $rolls): Response
{
    try {
        $result = $rolls->roll($dice->expression($request->request->all('dice')));
    } catch (InvalidChoice) {
        throw new BadRequestHttpException();
    }

    return $this->render('roll.html.twig', ['result' => $result]);
}
```

`expression()` accepte un dé que la configuration offre, un nombre de 1 jusqu'à la limite de dés et un modificateur de
-1000 à 1000, en nombres entiers ou en leur texte, et lève `InvalidChoice` pour tout le reste. Avec une tour de dés,
lancez plutôt dans la tour de la table : `$tower->roll($table, $dice->expression(...), $member)`.

## Annoncer le résultat

La phrase nomme chaque dé — la face d'un dé personnalisé par son libellé —, dit un dé qui n'a pas compté, puis le total
ou ce que le jet a donné : `Aelis rolled. 2d6+1: 4, 3. Total 8.` Le formulaire annonce le `result` qu'on lui donne ;
`accessible_dice_announcement(result, name)` donne la même phrase ailleurs, et `RollAnnouncer` en PHP.

## Les faces d'un dé personnalisé

```twig
{% for face in accessible_dice_faces('coin') %}
    <p>{{ face.description }}</p> {# Heads: a circle, worth 1 #}
{% endfor %}
```

## Avec une tour de dés

Le contrôleur écoute `tilttek--dice-tower--tower:drop` sur la fenêtre : chaque jet d'une tour de dés sur la même page
est annoncé dans la région dynamique du formulaire, ses faces nommées par les libellés du formulaire, son lanceur par
le nom que la page de la tour donne. Dessinez la tour avec `announce: false`, afin que chaque jet soit annoncé une seule
fois :

```twig
{{ dice_tower(table, names, announce: false) }}
{{ accessible_dice(path('table_roll', {table: table.id})) }}
```
