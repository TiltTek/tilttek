<!-- phil-dev source-sha256=19e03f0be1ce8f52a4a17a6476a1c7eb6c02d1a055420965b20dfb148c9c71b1 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau, sur le dépôt en
mémoire et le `MockClock` de `symfony/clock` : former, renommer, changer de système et dissoudre ; rejoindre, aux
limites des bornes, les rôles gardés une fois et bornés, le statut, quitter avec le butin qui retourne à la réserve ;
les dépôts, les retraits et les bornes de la bourse, les parts entre les membres actifs ou choisis avec leur reste, la
distribution ; le butin ajouté, donné, remis à la réserve et retiré ; les notes écrites, modifiées et retirées ;
l'ordre de lecture des membres, du butin, des notes et des groupes ; l'effacement d'un membre de tous les groupes et de
ses notes ; la composition — rôles manquants, couverts et surchargés, juste part et minimums, membres en absence,
membres sans rôle et rôles inconnus, catalogues tirés des termes d'un système, un jeu sans rôles qui ne reçoit aucun conseil — ; et les événements communiqués. Dans
le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

## Tester du code qui utilise des groupes

Construisez le service sur le dépôt en mémoire et une horloge que le test fait avancer :

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Party\Parties;
use Tilttek\Party\Store\InMemoryPartyStore;

$clock = new MockClock('2026-12-01 19:00:00', 'UTC');
$parties = new Parties(new InMemoryPartyStore(), $clock);

$parties->form($party, $campaign, 'The Lantern Bearers');
$clock->modify('+1 week');
$parties->join($party, $character, ['role.healer']);
```

Un `Contract\PartyObserver` qui garde les événements qu'il reçoit permet à un test de lire ce qui a été communiqué. Un
dépôt de l'application passe les mêmes tests quand il remplace le dépôt en mémoire dans une copie de la suite.
