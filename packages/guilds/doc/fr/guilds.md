<!-- phil-dev source-sha256=f48f7bf49dde6c6e8f1ceeb618e0f5d6381f21fdb5c1f7af8431226551f502b4 -->
# Guildes et membres

Une guilde est une communauté durable de membres, désignée par la référence que l'application lui donne — `guild:44`,
`club:0192f3c4-…` —, avec son nom, son admission, sa capacité et ses métadonnées. `Guilds` est le service qui les gère
sur un `Contract\GuildStore`.

## Fonder

```php
$guilds->found(
    ResourceReference::of('guild', '44'),
    'The Lantern Bearers',
    $founder,
    Admission::OnRequest,                       // Admission::Open by default
    20,                                         // 50 by default
    new Metadata(['motto' => 'Light the way']),
);
```

Le fondateur est le premier membre de la guilde. Une référence se fonde une seule fois : la fonder de nouveau est
refusé avec `Refusal::AlreadyFounded`.

| Valeur | Bornes |
|---|---|
| Nom | 1 à 100 caractères UTF-8, sans caractère de contrôle, sans espace autour |
| Capacité | 2 à 1000 membres, 50 par défaut |
| Métadonnées | au plus 20 entrées ; un nom de 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, commençant par une lettre ; une valeur d'au plus 500 octets UTF-8 sans caractère de contrôle |

La bibliothèque ne lit aucune des métadonnées : elles appartiennent à l'application, qui s'en sert pour afficher une
devise, l'identifiant d'une bannière ou une langue.

## Admission

| Admission | `join()` retourne | Ce qui suit |
|---|---|---|
| `Admission::Open` | un `Membership` | Le membre est dans la guilde aussitôt. |
| `Admission::OnRequest` | un `JoinRequest` | Le fondateur ou un administrateur appelle `approve()` ou `decline()` ; le membre peut retirer sa demande avec `withdraw()`. |

`requests($guild, $by)` liste les demandes en attente, la plus ancienne d'abord, pour le fondateur et les
administrateurs seulement. Passer une guilde de « sur demande » à « ouverte » laisse ses demandes en attente à approuver
ou à refuser.

## Rôles

| Rôle | Peut |
|---|---|
| `Role::Founder`, un par guilde | tout ce qui suit ; promouvoir et rétrograder ; remettre la guilde ; la dissoudre |
| `Role::Administrator` | approuver et refuser les demandes ; retirer des membres ; modifier la guilde ; ouvrir et fermer des objectifs |
| `Role::Member` | partir ; contribuer aux objectifs de la guilde |

```php
$guilds->promote($guild, $member, $founder);   // member → administrator
$guilds->demote($guild, $member, $founder);    // administrator → member
$guilds->transfer($guild, $heir, $founder);    // the heir becomes the founder, the founder an administrator
$guilds->remove($guild, $member, $by);         // the founder removes anyone else, an administrator members only
$guilds->leave($guild, $member);               // the founder hands the guild over first
$guilds->dissolve($guild, $founder);           // the guild, its members and its requests removed
```

Un acte que le rôle n'autorise pas est refusé avec `Refusal::NotAllowed` ; un acte par une personne extérieure à la
guilde, ou sur elle, avec `Refusal::NotMember`.

## Capacité et limites

Une guilde pleine refuse quiconque la rejoint et toute approbation avec `Refusal::Full`. Abaisser sa capacité sous le
nombre de membres qu'elle compte est refusé avec `Refusal::BelowMembers` : la bibliothèque ne retire jamais personne
pour faire de la place, et ne retire jamais un membre pour cause d'inactivité.

`Limits` borne ce que couvrent les lectures :

| Limite | Valeur par défaut | Bornes | Refus |
|---|---|---|---|
| Guildes auxquelles un membre appartient ou a demandé à se joindre | 10 | 1 à 100 | `Refusal::TooManyGuilds` |
| Demandes en attente d'une guilde | 100 | 1 à 1000 | `Refusal::TooManyRequests` |
| Objectifs ouverts d'une guilde | 10 | 1 à 100 | `Refusal::TooManyGoals` |

La capacité est vérifiée avant l'écriture de l'adhésion : une application qui sert des requêtes concurrentes exécute
chaque acte dans une transaction, ou sous un verrou sur la guilde, afin que deux membres ne prennent jamais ensemble la
dernière place.

## Lire

| Méthode | Retourne |
|---|---|
| `guild($guild)` | Le `Guild`, ou `UnknownGuild` |
| `membership($guild, $member)` | Le `Membership`, ou null |
| `isMember($member, $guild)` | Si le membre appartient à la guilde |
| `members($guild)` | Les adhésions : le fondateur, puis les administrateurs, puis les membres, chacun dans l'ordre d'entrée, le plus ancien d'abord |
| `references($guild)` | Les références des membres, le plus ancien entré d'abord |
| `guildsOf($member)` | Les adhésions du membre, la plus ancienne d'abord |

Les membres entrés au même moment se lisent dans l'ordre des octets de leurs références.

## Ce qu'on annonce

Chaque acte annonce aux observateurs un `GuildChanged` : la guilde, le `Change`, le membre concerné, le membre qui a
agi et le moment — des identifiants seulement.

| Changement | Membre | Auteur |
|---|---|---|
| `Founded` | le fondateur | aucun |
| `Joined`, `Requested`, `Withdrawn`, `Left` | le membre | aucun |
| `Approved`, `Declined`, `Removed`, `Promoted`, `Demoted` | le membre | le fondateur ou un administrateur |
| `Transferred` | le nouveau fondateur | l'ancien fondateur, aucun après un effacement |
| `Updated` | aucun | le fondateur ou un administrateur |
| `Dissolved` | aucun | le fondateur, aucun après un effacement |

## Effacement

`erase($member)` retire les demandes en attente et les adhésions du membre. Une guilde qu'il a fondée passe à son plus
ancien administrateur, sinon à son plus ancien membre, et est dissoute quand il en était le seul membre. Ses points dans
les objectifs de la guilde restent, sans lui : voyez [la piste de groupe](goals.md#effacement).
