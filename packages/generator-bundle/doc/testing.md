# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --test tests/js/
```

The unit tests cover the configuration and the services, the cached catalog, the generator factory and its dice, the
access to the API, the reading of a draw, the controller and each of its answers, the rate limiter, the commands and the
Twig function. The functional test boots a small application — FrameworkBundle with its router and a rate limiter,
TwigBundle and the bundle — and draws through the API, a template and the command. The Node tests cover the pure
functions of the Stimulus controller. In the monorepo, Infection holds the bundle at 100 % of covered mutants.

## Testing an application's generators

Run `bin/console tilttek:generator:validate` in the continuous integration, or build the generator in a test:

```php
use Tilttek\Generator\Generator;

public function testTheGeneratorsAreValid(): void
{
    self::bootKernel();
    $generator = self::getContainer()->get(Generator::class);

    foreach (array_keys($generator->catalog()->generators()) as $name) {
        $generator->draw($name);
    }
}
```
