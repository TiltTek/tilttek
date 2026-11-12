<!-- phil-dev source-sha256=e4cf2945790c6f23e0cb7347bc3feacf2c9ce81699b179d9b47ea98e011985b9 -->
# Tables à mémoire

Une table à mémoire est un générateur de `tilttek/generator` muni d'un état (`state`) : chaque tirage repart de l'état
que le précédent a laissé. La bibliothèque ne garde rien entre les tirages ; le bundle garde les états, par générateur
et par clé (une campagne, une table, une personne, comme l'application les nomme).

## Tirer

`MemoryTables` est un service public, enregistré quand un stockage des états existe :

```php
use Tilttek\GeneratorBundle\Application\MemoryTables;

final readonly class CampaignWeather
{
    public function __construct(private MemoryTables $tables)
    {
    }

    public function nextDays(string $campaign): array
    {
        return $this->tables->draw('weather', 'campaign-'.$campaign, count: 3)->results;
    }
}
```

| Méthode | Ce qu'elle fait |
|---|---|
| `draw($generator, $key, $count, $unique, $seed)` | Tire à partir de l'état gardé sous la clé (aucun au premier tirage) et garde l'état que le tirage laisse. Un générateur sans état tire comme d'habitude et ne garde rien. |
| `state($generator, $key)` | L'état gardé sous la clé, ou `null`. |
| `set($generator, $key, $state)` | Fixe l'état, lu comme `Draw` en lit un : un nouveau cap, un facteur de chaos monté par une scène. |
| `forget($generator, $key)` | Oublie l'état : le tirage suivant recommence. |

Une clé est un texte de 1 à 200 caractères, tout caractère compris ; une clé vide ou plus longue est une
`InvalidStateKey`.

## Le verrou

Sans verrou, deux tirages sur le même générateur et la même clé au même moment partent du même état, et le dernier
gardé l'emporte : l'autre jour de météo, l'autre étape du voyage, est perdu. Une application dont les tirages peuvent
se croiser (deux personnes à la même table, deux *workers*) nomme une fabrique de verrous de `symfony/lock` :

```yaml
framework:
    lock: '%env(LOCK_DSN)%'

tilttek_generator:
    state:
        lock: lock.factory
```

`MemoryTables` tient alors le verrou du générateur et de la clé du chargement de l'état à l'enregistrement du nouveau :
le deuxième tirage attend le premier et continue à partir de l'état qu'il a laissé. `set()` et `forget()` prennent le
même verrou. Le verrou est nommé d'après une empreinte du générateur et de la clé, attendu, et libéré à la fin du
tirage, par un résultat ou par une exception ; un processus qui meurt en le tenant fait attendre les autres 30 secondes
au plus. Le stockage du verrou doit être partagé par les processus qui tirent (un stockage *flock* pour un serveur,
Redis, Valkey ou une base de données pour plusieurs), comme l'explique la documentation de Symfony.

Le verrou est désactivé par défaut, et `symfony/lock` est seulement suggéré : une application qui tire depuis un seul
processus à la fois n'a besoin ni de l'un ni de l'autre. Une application qui garde ses verrous ailleurs implémente le
port `StateLock` et le passe à `MemoryTables` comme troisième argument.

## Le stockage

Les états passent par le port `StateStore`, choisi par `state.store` :

| Stockage | Où sont les états |
|---|---|
| `cache` (par défaut) | Dans le pool PSR-6 de `state.pool`, `cache.app` par défaut, sous une clé hachée du générateur et de la clé, sans expiration. `cache.app` oublie les états quand le cache est vidé : donnez aux états un pool à part sur Doctrine DBAL ou Valkey pour les garder d'un déploiement à l'autre ([Configuration](configuration.md)). |
| `memory` | En mémoire, pour les tests : oubliés à la fin du processus et quand le noyau est réinitialisé. |
| un identifiant de service | Le `StateStore` propre à l'application : une table à elle sur Doctrine, une colonne d'une entité de campagne, un service distant. |

Un stockage de l'application implémente trois méthodes :

```php
use Tilttek\GeneratorBundle\Application\Contract\StateStore;

final readonly class CampaignStates implements StateStore
{
    public function load(string $generator, string $key): ?array { /* … */ }

    public function save(string $generator, string $key, array $state): void { /* … */ }

    public function forget(string $generator, string $key): void { /* … */ }
}
```

## Par l'API et le terminal

L'API de tirage ne garde aucun état : une table à mémoire répond son nouvel état avec ses résultats, et le client le
renvoie comme `state[name]=value` avec son tirage suivant ([API de tirage](api.md)). La commande prend `--key` pour
tirer à partir d'un état gardé et `--reset` pour recommencer ([Commandes](commands.md)).
