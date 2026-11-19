# Reference

| Type | Role |
|---|---|
| `Jail\Warden` | Jails and releases dice, lists those in jail, gives the history |
| `Jail\Inmate` | A die in jail: its jailing read as a sentence |
| `Jail\JailEvent` | One line of a die's history |
| `Jail\EventKind` | `Jailed` or `Released` |
| `Jail\Evidence` | The statistics shown when a die went to jail |
| `Statistics\GoodnessOfFit` | Pearson's chi-squared test of one die |
| `Statistics\FitResult` | The test's statistic, degrees, p-value, verdict, faces and means |
| `Statistics\FaceCount` | One face: observed and expected |
| `Statistics\Verdict` | `NotEnoughRolls`, `NothingUnusual`, `SomewhatUnusual` or `VeryUnusual` |
| `Statistics\Tally` | How many times each face came up |
| `Statistics\ExpectedFaces` | The exact probabilities of a fair die |
| `Statistics\Explanation` | The verdict in plain English or French |
| `Statistics\Language` | `English` or `French` |
| `Statistics\Origin` | `Physical` or `Digital` |
| `Statistics\DieAssessor` | The test from the rolls a source recorded |
| `Contract\JailStore` | Where the histories are kept |
| `Contract\RollSource` | Where the rolls of a die are read |
| `Store\InMemoryJailStore` | The histories in memory |
| `Store\InMemoryRollSource` | The rolls in memory |
| `Exception\DiceJailException` | The interface of every exception of the library |
| `Exception\InvalidRolls` | Rolls or faces the test cannot judge |
| `Exception\InvalidJailEntry` | A keeper, a die or a reason the jail refuses |
| `Exception\JailRefused` | A step the jail does not allow |
| `Exception\JailConflict` | Two writers changed a die's history at once |

`Statistics\ChiSquared` is internal: the upper tail of the chi-squared law.
