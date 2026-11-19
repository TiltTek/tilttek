# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\JailStore` | The storage of the history of each die, with its positions | [The jail](jail.md#concurrent-writers) |
| `Contract\RollSource` | The rolls of each die: the faces players wrote down, or the application's journal of rolls | [The statistics](statistics.md#reading-them-honestly) |
| `Psr\Clock\ClockInterface` | The time of each event and of the end of sentences | [Installation](installation.md#wiring-the-library) |
| `Statistics\ExpectedFaces` | The fair die the rolls are measured against | [The statistics](statistics.md#the-test) |

Every point is passed to a constructor; no service keeps state between two calls.
