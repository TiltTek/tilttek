<!-- phil-dev source-sha256=cf8bdbed12d0ca0072d2c3c4ed10ab4875aeb2d0e062efa0d6b729d8077ab38e -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --test tests/js/
```

Les tests unitaires couvrent la configuration et les services, le catalogue en cache, la fabrique de générateurs et ses
dés, l'accès à l'API, la lecture d'un tirage, le contrôleur et chacune de ses réponses, le limiteur de débit, les
commandes et la fonction Twig. Le test fonctionnel démarre une petite application (FrameworkBundle avec son routeur et
un limiteur de débit, TwigBundle et le bundle) et fait des tirages par l'API, un gabarit et la commande. Les tests Node
couvrent les fonctions pures du contrôleur Stimulus. Dans le monodépôt, Infection maintient le bundle à 100 % des
mutants couverts.

## Tester les générateurs d'une application

Lancez `bin/console tilttek:generator:validate` dans l'intégration continue, ou construisez le générateur dans un test :

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
