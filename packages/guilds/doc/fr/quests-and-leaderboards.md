<!-- phil-dev source-sha256=b67b086b446ca11f81af0402ac9ee04ffe6cb5a7130aaef5f632f435e9643993 -->
# Quêtes et classements

Une guilde fournit le cercle de ses membres ; les autres paquets décident ce que ce cercle fait.

## L'équipe d'une quête

`tilttek/quests` demande à un `TeamMembership` si un membre appartient à l'équipe sous laquelle il se joint à une quête
d'équipe. `GuildTeamMembership` répond à partir du magasin de guildes, jamais d'après une équipe que le navigateur a
nommée :

```php
use Tilttek\Guilds\GuildTeamMembership;
use Tilttek\Quests\Quests;

$quests = new Quests($evaluator, $clock, new GuildTeamMembership($guildStore));

$quests->accept($member, 'winter-campaign', ResourceReference::of('guild', '44'));
```

La quête d'équipe reste dans `tilttek/quests` : ses étapes, son calendrier et ses récompenses. La guilde dit seulement
qui en fait partie.

## Le cercle d'un classement

`tilttek/leaderboards` classe un tableau parmi un `Circle` de membres, ou additionne les scores d'une `Team`. Les
membres de la guilde fournissent les deux :

```php
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Team;

$circle = new Circle(...$guilds->references($guild));
$leaderboards->standing('games-played', $viewer, among: $circle);

$leaderboards->teams('games-played', [new Team('lanterns', $circle)]);
```

Une guilde compte au plus 1000 membres, comme un cercle. `tilttek/leaderboards` est suggéré, non exigé : une
application sans classements n'installe rien de plus.
