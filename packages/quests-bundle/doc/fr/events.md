<!-- phil-dev source-sha256=d942e1c962becbed3b87c63019bd07f2907e5963c972b4de2c93dba86780e28c -->
# Événements

| Événement | Réparti quand |
|---|---|
| `Tilttek\Quests\QuestCompleted` | Une quête est accomplie : la quête, l'accomplissement, `rewards()` et `beneficiaries` |
| `Tilttek\QuestsBundle\Infrastructure\Event\DuelWon` | Un camp a accompli un duel en premier, juste après son `QuestCompleted` |
| `Tilttek\QuestsBundle\Infrastructure\Event\DuelExpired` | Le temps d'un duel s'est écoulé sans gagnant, ou son offre est restée sans réponse |

## Donner les récompenses

Les récompenses sont déclarées, jamais exécutées : l'application les donne par les paquets dont elle dispose.

```php
#[AsEventListener]
public function __invoke(QuestCompleted $event): void
{
    foreach ($event->beneficiaries as $member) {
        foreach ($event->rewards() as $reward) {
            match ($reward->kind) {
                'points' => $this->points->credit($member, $reward->amount, $reward->code, 'quest:'.$event->completion->quest.':'.$event->completion->occurrence),
                'badge' => $this->badges->award($member, $reward->code),
                default => null,
            };
        }
    }
}
```

Dérivez la clé d'un mouvement de points de l'accomplissement (quête, occurrence, membre) : ainsi, la donner deux fois
ne compte qu'une fois. Une reconstruction ne répartit pas de nouveau `QuestCompleted` pour une quête accomplie de
nouveau.
