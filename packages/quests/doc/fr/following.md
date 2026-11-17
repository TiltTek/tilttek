<!-- phil-dev source-sha256=7cd8213b812c9f0e5d03831185a9258a133b2a77e87e5ebad007d7df4b8fe3dc -->
# Suivre une quête

## Admission

- **`Automatic`** : chaque membre suit la quête dès qu'elle est ouverte, sans le demander ; toute son activité dans
  l'occurrence compte.
- **`OnAcceptance`** : seule l'activité après l'acceptation de la quête par le membre compte, et seule l'activité des
  membres qui l'ont acceptée est gardée.

Une quête d'équipe et un duel se rejoignent toujours par acceptation : la première sous une équipe dont le membre fait partie, le
second quand un membre accepte un défi.

```php
$quests = new Quests($evaluator, $clock, $teamMembership);
$quests->accept($member, 'winter-reading');
$quests->accept($member, 'raid', ResourceReference::of('team', 'red'));
$quests->abandon($member, 'winter-reading');
```

L'acceptation est refusée quand la quête est un duel, fermée, verrouillée derrière la quête qu'elle suit, déjà accomplie
dans son occurrence, une quête d'équipe sans équipe ou une autre quête avec une équipe. Accepter une quête déjà suivie
retourne l'inscription telle quelle ; un membre d'équipe qui veut une autre équipe abandonne d'abord la quête.

### Appartenance à l'équipe

Avant qu'un membre rejoigne une quête d'équipe sous une équipe — et chaque fois qu'il l'accepte de nouveau —, `Quests`
demande au `TeamMembership` qu'on lui a donné si le membre fait partie de cette équipe, et refuse la quête avec
`QuestRefused` quand ce n'est pas le cas. L'application l'implémente sur ses propres dossiers d'équipes, côté serveur :
l'équipe qu'une requête nomme n'est jamais prise pour une preuve. Sans lui, `Quests` emploie `NoTeamMembership`, sous
lequel aucun membre ne fait partie d'aucune équipe, si bien qu'une quête d'équipe n'est jamais rejointe par défaut.

```php
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Contract\TeamMembership;

final readonly class GuildMembership implements TeamMembership
{
    public function __construct(private GuildRepository $guilds)
    {
    }

    public function isMember(ResourceReference $member, ResourceReference $team): bool
    {
        return 'guild' === $team->key && $this->guilds->hasMember($team->id, $member->toString());
    }
}
```

Un membre qui quitte l'équipe garde l'inscription qu'il avait ; l'application l'abandonne pour lui quand il part.

## Abandon

Abandonner fait cesser de compter l'activité du membre à partir de ce moment. Abandonner une quête suivie
automatiquement écrit une inscription pour elle ; l'accepter de nouveau la reprend avec tout ce qu'elle avait compté.
Abandonner une quête acceptée, puis l'accepter de nouveau, compte à partir de la nouvelle acceptation. Une quête
accomplie ne peut plus être abandonnée.

## Où en est une quête

`Quests::status($member, $code)` lit un `QuestStatus` : la quête, son `QuestState`, son occurrence ouverte maintenant,
la progression de son sujet (le membre, l'équipe du membre ou la communauté), l'accomplissement qui l'a terminée, et
l'équipe.

| État | Quand |
|---|---|
| `Closed` | Pas ouverte maintenant, et pas accomplie dans sa dernière occurrence |
| `Locked` | Ouverte, mais la quête qu'elle suit n'est pas encore accomplie |
| `Available` | Ouverte, en attente de l'acceptation du membre, ou de son arrivée sous une équipe ; un duel ouvert maintenant |
| `UnderWay` | Suivie, ses étapes avancent |
| `Abandoned` | Le membre l'a abandonnée |
| `Completed` | Accomplie dans son occurrence en cours, ou dans sa dernière une fois fermée |

`Quests::board($member)` lit l'état de chaque quête, sauf les duels ; `Quests::completions($subject)` lit le journal
des quêtes qu'un membre, une équipe ou la communauté a accomplies.
