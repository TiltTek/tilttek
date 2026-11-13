<!-- phil-dev source-sha256=f705d52187dc1c07b34b71621be41e6aea04cf65beea3db3dc156ccae7732b2d -->
# Tables en direct

La page d'un joueur montre la table, s'abonne à son sujet Mercure, et relit la vue de son siège à chaque mise à jour.
La mise à jour ne porte que des identifiants et des compteurs ; la vue est lue depuis le serveur, qui vérifie qui la lit.

## La publication

Avec `mercure.enabled`, chaque changement d'une table — ouverte, un coup, un coup repris — est publié sur son sujet,
`urn:tilttek-game-engine:table:{table}` sauf configuration contraire, en privé sauf si `mercure.private` est désactivée :

```json
{"table":"table:42","sequence":12,"phase":"race","round":4,"to_act":["seat:2"],"over":false}
```

Le coup est entré dans le journal quand la mise à jour est publiée : un concentrateur qui échoue est journalisé comme
un avertissement, non levé, et les joueurs relisent la table d'eux-mêmes. Une mise à jour privée n'atteint que les
abonnés dont le concentrateur a signé le jeton pour le sujet ; l'application autorise chaque joueur pour les sujets de
ses tables, avec la fonction Twig `mercure()` de MercureBundle ou son service `Authorization`.

## Le lecteur

Les points de terminaison demandent à l'application qui lit une table, par `Application\Contract\ViewerResolver` :

```php
final readonly class SeatOfTheMember implements ViewerResolver
{
    public function __construct(private Security $security, private Seating $seating)
    {
    }

    public function resolve(string $gameId): Viewer
    {
        $member = $this->security->getUser();
        $seat = null === $member ? null : $this->seating->seatOf($gameId, $member);

        return null === $seat ? Viewer::denied() : Viewer::seat($seat, $this->seating->preferences($member));
    }
}
```

`Viewer::seat()` nomme un siège et les préférences que son joueur a choisies, `Viewer::audience()` le public, qui voit
ce que le jeu montre à un siège null, et `Viewer::denied()` personne. Sans résolveur, `view.audience` ouvre la vue du
public de chaque table à tous ; sinon, personne ne lit une table.

## Les points de terminaison

| Route | Chemin | Ce qu'elle répond |
|---|---|---|
| `tilttek_game_engine_view` | `GET /{table}` | La vue du siège du lecteur et ce que tous ceux de la table peuvent savoir |
| `tilttek_game_engine_log` | `GET /{table}/log` | Le journal d'une table dont la partie est terminée, germe compris |

La vue répond la table, son jeu, le nombre de coups, la phase, la ronde, les sièges qui peuvent agir, l'issue et ses
statistiques une fois la partie terminée, le siège du lecteur, l'engagement sur le germe, le sujet Mercure et la vue
elle-même :

```json
{
    "table": "table:42", "game": "roll-race", "sequence": 12, "phase": "race", "round": 4,
    "to_act": ["seat:2"], "over": false, "outcome": null, "seat": "seat:1",
    "commitment": "9f2c…", "topic": "urn:tilttek-game-engine:table:table:42",
    "view": {"totals": {"seat:1": 9, "seat:2": 7}, "secret": 54}
}
```

Le journal est refusé avec 409 tant que la partie continue : son germe dirait les tirages à venir. Il est refusé avec 409
aussi quand le journal lu n'est pas celui de l'état terminé — une table rouverte sous son identifiant entre les deux
lectures —, de sorte que le germe d'une table qui continue n'est jamais montré. Une fois la partie
terminée, il répond le journal en valeurs simples avec son engagement, que chacun rejoue pour vérifier que chaque
tirage venait du germe montré aux joueurs à l'ouverture — `tilttek:game-engine:verify` le fait sur le serveur.

Les deux répondent 403 quand le résolveur refuse le lecteur ou nomme un siège qui n'est pas à la table, 404 pour une
table qui n'existe pas, et 500 pour une table dont le journal ne peut pas être rejoué ; chaque réponse est du JSON,
avec `Cache-Control: no-store, private`, et une erreur est `application/problem+json`.

Faire des coups est le travail du contrôleur de l'application : il vérifie le joueur, puis appelle
`GameTables::play()` avec le siège qu'il connaît et le nombre de coups que le joueur a vus. `GameTables::undo()` reprend le dernier coup de la même façon, et lève
`GameOver` une fois la partie terminée : le point de terminaison du journal a alors pu montrer le germe, qui dit chaque
tirage à venir.

## Le gabarit et le contrôleur Stimulus

`@TilttekGameEngine/table.html.twig` montre une table comme tous ceux qui y sont peuvent la voir — son nombre de coups,
sa phase, sa ronde, les sièges qui peuvent agir, son issue une fois terminée, l'engagement sur son germe — construite
avec les recettes Card et Badge du kit, et l'endroit où va la vue :

```twig
{% set table = tilttek_game_table('table:42') %}
{{ include('@TilttekGameEngine/table.html.twig', {
    table: table,
    view_url: path('tilttek_game_engine_view', {table: 'table:42'}),
    hub_url: mercure(table.topic, {subscribe: table.topic}),
}) }}
```

Le contrôleur Stimulus `game-table` lit la vue depuis `view_url`, s'abonne à `hub_url` quand on le donne, et relit la
vue à chaque mise à jour dont le nombre de coups diffère de celui de la page — un coup plus récent, ou un coup repris.
Il montre le nombre de coups, la phase, la ronde et les sièges qui peuvent agir dans ses cibles sous forme de texte, et
envoie la réponse comme l'événement `game-table:view` ; l'application produit le rendu de la vue de son jeu en
l'écoutant :

```js
element.addEventListener('tilttek--game-engine-bundle--game-table:view', (event) => render(event.detail.view));
```

| Fonction Twig | Ce qu'elle donne |
|---|---|
| `tilttek_game_table(table)` | Ce que tous ceux de la table peuvent savoir, jamais les données du jeu |
| `tilttek_game_engine_kit(recipe)` | Le nom sous lequel une recette du kit est installée |
| `tilttek_game_engine_domain()` | Le domaine de traduction du gabarit |

Les textes du gabarit sont dans le domaine `tilttek_game_engine`, en anglais et en français ; le nom d'un jeu est
`games.<name>`.
