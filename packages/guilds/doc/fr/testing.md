<!-- phil-dev source-sha256=7a8d77a06e40e36a78fdb4cbc6bdb2b481c123ba5ace4d404cc3fe7736005b00 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau, sur les magasins en
mémoire et le `MockClock` de `symfony/clock` : fonder, rejoindre des guildes ouvertes et demander à rejoindre des
guildes sur demande ; approuver, refuser et retirer des demandes ; la capacité, à sa limite, et les limites de guildes
par membre et de demandes en attente ; les rôles — qui peut retirer, promouvoir, rétrograder, transférer, modifier et
dissoudre —; l'ordre de lecture des membres et des guildes ; l'effacement d'un membre, un fondateur qui remet la guilde
à son plus ancien administrateur ou membre, une guilde dissoute avec son seul membre ; des objectifs ouverts,
alimentés sous des clés et sans clé, atteints à leur cible, refusés à leur échéance à la seconde près ou une fois
fermés, bornés par guilde ; des points gardés après le départ ou l'effacement d'un membre ; les événements annoncés ; et
une guilde comme équipe d'une quête et cercle d'un classement. Dans le monodépôt, Infection maintient la bibliothèque à
100 % des mutants couverts.

## Tester du code qui utilise des guildes

Construisez les services sur les magasins en mémoire et une horloge que le test déplace :

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Guilds\Goals;
use Tilttek\Guilds\Guilds;
use Tilttek\Guilds\Store\InMemoryGoalStore;
use Tilttek\Guilds\Store\InMemoryGuildStore;

$clock = new MockClock('2026-12-01 10:00:00', 'UTC');
$guilds = new Guilds(new InMemoryGuildStore(), $clock);
$goals = new Goals(new InMemoryGoalStore(), $guilds, $clock);

$goals->open('december', $guild, 'Play 10 games', 10, $founder, new DateTimeImmutable('2026-12-02 10:00:00'));
$clock->modify('+1 day'); // the deadline has passed
```

Un magasin de l'application passe les mêmes tests quand il remplace le magasin en mémoire dans une copie de la suite.
