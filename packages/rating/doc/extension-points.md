# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\RatingStore` | The storage of the members' ratings | [Storing](storing.md) |
| `Contract\PlaySource` | The results of a game, oldest first, for a replay | [Replaying](replaying.md#the-source) |
| `Contract\RatingSystem` | Another rating system; `Elo\EloSystem` and `OpenSkill\OpenSkillSystem` are given | [Elo](elo.md), [OpenSkill](openskill.md) |
| `Elo\KFactor` | A fixed or decreasing K | [Elo](elo.md#the-k-factor) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Who hears of each change, through `Event\RatingChanged` and `Event\PeakReached` | [Celebrations](celebrations.md) |

A system of the application implements `Contract\RatingSystem` and `Contract\Rating`: an identifier of its own, kept
with each rating, ratings of whole numbers and the same new ratings for the same inputs. Every point is passed to a
constructor; none of the library's services keeps state between two calls.
