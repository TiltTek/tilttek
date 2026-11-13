<!-- phil-dev source-sha256=4fe6cdf1e262b88a30d14fd7f06d09ab74fe14761804164148f2be5dcaec67ba -->
# tilttek/secret-rolls

**Jets cachés du meneur de jeu, vérifiables et révélés après coup.**

Un meneur de jeu lance les dés hors de vue — un test de perception, un monstre errant, un piège que les joueurs n'ont
pas trouvé — et la table ne voit, à ce moment, qu'un engagement : le SHA-256 de tout ce que le jet est, son germe secret
de 32 octets compris. Le résultat reste scellé dans le dépôt de stockage. Quand le meneur de jeu révèle le jet, pendant
la scène ou à la fin, l'ouverture est publiée, et tout joueur vérifie seul qu'elle a pour empreinte l'engagement montré
plus tôt et que relancer l'expression avec son germe donne le résultat révélé : le meneur de jeu n'a pu ni choisir le
résultat ni le changer ensuite.

Les jets d'une scène forment un journal public dont les entrées sont chaînées par leurs empreintes : un jet engagé, un
jet révélé, la scène close. Une scène close n'accepte plus de jets ; le meneur de jeu révèle les jets qu'il choisit, ou
tous, et les autres restent retenus, leurs engagements gardés dans le journal, encore révélables plus tard. Le journal
s'exporte en JSON, le fichier qu'un joueur télécharge et vérifie en entier avec `SceneJournal::fromJson()->problems()`,
ou avec n'importe quel outil qui calcule du SHA-256 et relance `tilttek/dice`.

Le paquet s'adresse à une application où un meneur de jeu lance les dés pour la table à distance — une partie par
messages, une table de jeu virtuelle, une tour de dés montrée en diffusion en direct — et où les joueurs veulent se
fier aux jets qu'ils ne voient pas. C'est une bibliothèque PHP simple, construite sur `tilttek/dice`, qui lit la
notation et lance les dés à partir d'un germe ; l'application garde les journaux et les jets scellés dans son propre
stockage et pousse chaque entrée vers la table par un port de publication.

## État

| Partie | État |
|---|---|
| `SecretRolls` : les jets cachés, les révélations partielles et totales, la clôture d'une scène | Implémenté et testé |
| `HiddenRoll`, `RollOpening`, `RollCommitment` : le jet scellé, son ouverture et son engagement SHA-256 | Implémenté et testé |
| `RollVerifier` : une ouverture vérifiée avec son engagement, et relancée à partir de son germe | Implémenté et testé |
| `Journal\SceneJournal`, `Journal\JournalEntry`, `Journal\EntryKind` : le journal public chaîné, son export JSON et sa vérification | Implémenté et testé |
| `Contract\SecretRollStore`, `Contract\JournalPublisher`, `Store\InMemorySecretRollStore`, `Store\NoPublisher` | Implémenté et testé |
| Exceptions sous une seule interface `SecretRollsException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Jets cachés](doc/fr/hidden-rolls.md) | Lancer hors de vue, révéler, clore une scène, ce que la table voit et quand. |
| [Vérifier](doc/fr/verifying.md) | L'engagement, les empreintes du journal, l'export, et la façon dont un joueur le vérifie seul. |
| [Stocker et publier](doc/fr/storing-and-publishing.md) | Le contrat du dépôt de stockage, les écritures concurrentes et le port de publication. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `SecretRollsException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, ses cibles de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\SecretRolls\SecretRolls;
use Tilttek\SecretRolls\Store\InMemorySecretRollStore;

$rolls = new SecretRolls(new InMemorySecretRollStore());

$hidden = $rolls->roll('scene:12', '1d20+4', roller: 'seat:gm', context: 'Do the guards notice the thief?');
$hidden->opening->totals();                       // [17], for the game master's eyes
$hidden->commitment()->hex;                       // what the table sees now

$rolls->close('scene:12', $hidden->roll());       // reveals it, then closes the scene
$export = $rolls->journal('scene:12')->toJson();  // the file a player checks
```

## Prérequis

PHP 8.5 ou une version plus récente ; `tilttek/dice` 0.1.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
