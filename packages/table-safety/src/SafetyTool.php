<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

use Tilttek\TableSafety\Signal\SignalKind;

/**
 * The safety tools a table may choose. Each comes from a practice of the tabletop and live-action role-playing
 * communities; the package's documentation credits its authors.
 */
enum SafetyTool: string
{
    /** Before play: per theme, a line it never crosses or a veil it draws. */
    case LinesAndVeils = 'lines-and-veils';

    /** Before play: per theme, green, yellow or red. */
    case ConsentChecklist = 'consent-checklist';

    /** Before play: the questionnaire whose answers form the table's pact. */
    case SessionZero = 'session-zero';

    /** During play: take out what just happened, no explanation asked. */
    case XCard = 'x-card';

    /** During play: pause, rewind or fast-forward the scene. */
    case ScriptChange = 'script-change';

    /** During play: leave the game at any moment, by a discreet signal. */
    case OpenDoor = 'open-door';

    /** During play: the game master asks how everyone is doing, and each answers with a gesture. */
    case CheckIn = 'check-in';

    /** After play: what was enjoyed, and what one would like to see next time. */
    case StarsAndWishes = 'stars-and-wishes';

    /**
     * The people or the community the tool is credited to, as the documentation names them.
     */
    public function credit(): string
    {
        return match ($this) {
            self::LinesAndVeils => 'Ron Edwards',
            self::ConsentChecklist => 'Sean K. Reynolds and Shanna Germain',
            self::SessionZero, self::CheckIn => 'the tabletop role-playing community',
            self::XCard => 'John Stavropoulos',
            self::ScriptChange => 'Beau Jágr Sheldon',
            self::OpenDoor => 'Eirik Fatland',
            self::StarsAndWishes => 'Lu Quade',
        };
    }

    /**
     * The signals the tool raises during play; none for a tool used before or after.
     *
     * @return list<SignalKind>
     */
    public function signals(): array
    {
        return array_values(array_filter(SignalKind::cases(), fn (SignalKind $kind): bool => $kind->tool() === $this));
    }

    /**
     * Every tool, in the order of a game: before, during, after.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
