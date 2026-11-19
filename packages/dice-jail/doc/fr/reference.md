<!-- phil-dev source-sha256=b8de41797628147b3e0f41566509e4ab6d7764a61fdfe94b22790fcd3a0f9e45 -->
# Référence

| Type | Rôle |
|---|---|
| `Jail\Warden` | Emprisonne et libère des dés, liste ceux qui sont en prison, donne l'historique |
| `Jail\Inmate` | Un dé en prison : son emprisonnement lu comme une peine |
| `Jail\JailEvent` | Une ligne de l'historique d'un dé |
| `Jail\EventKind` | `Jailed` ou `Released` |
| `Jail\Evidence` | Les statistiques montrées quand un dé est allé en prison |
| `Statistics\GoodnessOfFit` | Le test du khi carré de Pearson sur un dé |
| `Statistics\FitResult` | La statistique du test, les degrés, la valeur p, le verdict, les faces et les moyennes |
| `Statistics\FaceCount` | Une face : observée et attendue |
| `Statistics\Verdict` | `NotEnoughRolls`, `NothingUnusual`, `SomewhatUnusual` ou `VeryUnusual` |
| `Statistics\Tally` | Combien de fois chaque face est sortie |
| `Statistics\ExpectedFaces` | Les probabilités exactes d'un dé équitable |
| `Statistics\Explanation` | Le verdict en français ou en anglais simples |
| `Statistics\Language` | `English` ou `French` |
| `Statistics\Origin` | `Physical` ou `Digital` |
| `Statistics\DieAssessor` | Le test à partir des lancers qu'une source a enregistrés |
| `Contract\JailStore` | Où les historiques sont gardés |
| `Contract\RollSource` | Où les lancers d'un dé sont lus |
| `Store\InMemoryJailStore` | Les historiques en mémoire |
| `Store\InMemoryRollSource` | Les lancers en mémoire |
| `Exception\DiceJailException` | L'interface de chaque exception de la bibliothèque |
| `Exception\InvalidRolls` | Des lancers ou des faces que le test ne peut pas juger |
| `Exception\InvalidJailEntry` | Un gardien, un dé ou une raison que la prison refuse |
| `Exception\JailRefused` | Une étape que la prison n'autorise pas |
| `Exception\JailConflict` | Deux rédacteurs ont changé l'historique d'un dé en même temps |

`Statistics\ChiSquared` est interne : la queue supérieure de la loi du khi carré.
