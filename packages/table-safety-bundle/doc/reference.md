# Reference

Every class of the bundle, with its role.

| Class | Role |
|---|---|
| `TilttekTableSafetyBundle` | The bundle and its configuration. |
| `Application\Contract\TableAccess` | Who takes part in a table and who runs it, from the application. |
| `Application\NoTableAccess` | The access of an application that named none: nobody. |
| `Application\SignalTopics` | The topics of a table's signals. |
| `Infrastructure\DependencyInjection\TableSafetyServices` | The services the configuration wires. |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Names the kit's recipes after the Toolkit's subdirectory. |
| `Infrastructure\Doctrine\TableSafetyTables` | The four tables. |
| `Infrastructure\Doctrine\TableSafetySchemaListener` | Adds the tables to the schema the ORM generates. |
| `Infrastructure\Doctrine\DbalTableStore`, `DbalParticipationStore`, `DbalSignalStore`, `DbalFeedbackStore` | The stores on Doctrine DBAL. |
| `Infrastructure\Doctrine\Rows` | Reading and writing the values of a row. |
| `Infrastructure\Form\ThemeAnswersType`, `ThemeAnswers` | The form of the lines and veils or the checklist, and its conversions. |
| `Infrastructure\Form\SessionZeroType` | The form of a session zero. |
| `Infrastructure\Form\StarsAndWishesType` | The form of the stars and wishes. |
| `Infrastructure\Form\SafetyToolsType` | The tools of an announcement. |
| `Infrastructure\Form\TableSettingsType` | The game master's form of the settings. |
| `Infrastructure\Http\TableController` | The table's screen and its signals. |
| `Infrastructure\Http\AnswersController` | A participant's answers, session zero and stars and wishes. |
| `Infrastructure\Http\GameMasterController` | The game master's screen. |
| `Infrastructure\Http\CardsController` | The printable cards of a table, as a page and as a PDF. |
| `Infrastructure\Http\Screens` | What the screens share: the table, the viewer, the CSRF token, the rendering. |
| `Infrastructure\Http\Views` | The arrays the templates draw from. |
| `Infrastructure\Print\SafetyCardsPdf` | The printable cards as a PDF, on `phil/paged-media`. |
| `Infrastructure\Mercure\MercureSignalObserver` | Pushes the signals to the hub. |
| `Infrastructure\Mercure\SignalSubscriptions` | The subscription of a viewer. |
| `Infrastructure\Privacy\TableSafetyPrivacy` | A participant's export and erasure for `phil/privacy-bundle`. |
| `Infrastructure\Console\PurgeSignalsCommand` | `tilttek:table-safety:purge-signals`. |
| `Infrastructure\Twig\TableSafetyExtension` | `table_safety_kit()` and `table_safety_tools()`. |
| `Infrastructure\Twig\KitComponents` | The names of the kit's recipes. |
