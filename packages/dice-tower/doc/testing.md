# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --test tests/js
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: the summary of a roll and its
bounds, the tower on the journal and the log in memory, the log on SQLite in memory, the relays at once and through a
bus, the publisher on a hub that keeps what it is given, the subscriptions and their cookie, the route of the missed
entries, the view of the overlay and the configuration. The functional test boots a small application — TilttekDiceBundle
with a coin, Twig and TwigComponent in French, the `Card` recipe of the PHIL UX kit copied where `ux:install` puts it,
and a hub that keeps what is pushed — and rolls, reads the route and draws the overlay. The scripts of the controller
are tested with Node's test runner. In the monorepo, Infection holds the package at 100 % of covered mutants.

## Testing an application that uses a tower

Keep the log and the journal in memory, and replace the hub by one that keeps what is pushed:

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

The memory stores are emptied between two requests of the test client, as in a worker: drop the rolls in the request
that reads them, or in the same test before the first request.
