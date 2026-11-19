<!-- phil-dev source-sha256=8e992d696263a3721001ca2114c4d3860292f155bf13b5a457debdf7853636be -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --test tests/js
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : le résumé d'un jet et
ses bornes, la tour sur le journal et le journal en mémoire, le journal sur SQLite en mémoire, les relais immédiats et
par un bus, l'éditeur sur un hub qui garde ce qu'on lui donne, les abonnements et leur témoin, la route des entrées
manquées, la vue de la surcouche et la configuration. Le test fonctionnel démarre une petite application —
TilttekDiceBundle avec une pièce, Twig et TwigComponent en français, la recette `Card` du kit PHIL UX copiée là où
`ux:install` la met, et un hub qui garde ce qui est poussé — puis lance un jet, lit la route et dessine la surcouche.
Les scripts du contrôleur sont testés avec l'exécuteur de tests de Node. Dans le monodépôt, Infection maintient le
paquet à 100 % des mutants couverts.

## Tester une application qui utilise une tour

Gardez le journal et le journal des dés en mémoire, et remplacez le hub par un hub qui garde ce qui est poussé :

```yaml
# config/packages/test/tilttek.yaml
tilttek_dice:
    store: memory
tilttek_dice_tower:
    store: memory
```

```php
final class RecordingHub implements Symfony\Component\Mercure\HubInterface
{
    /** @var list<Symfony\Component\Mercure\Update> */
    public array $updates = [];

    // getPublicUrl(), getFactory(), getProtocolVersion() and getCookieName() as the test needs them.

    public function publish(Symfony\Component\Mercure\Update $update): string
    {
        $this->updates[] = $update;

        return 'urn:uuid:'.count($this->updates);
    }
}
```

Les stockages en mémoire sont vidés entre deux requêtes du client de test, comme dans un *worker* : déposez les jets
dans la requête qui les lit, ou dans le même test avant la première requête.
