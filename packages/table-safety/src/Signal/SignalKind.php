<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Signal;

use Tilttek\TableSafety\SafetyTool;

/**
 * The signals raised during play, each from its tool.
 */
enum SignalKind: string
{
    /** The X-card: take out what just happened. */
    case XCard = 'x-card';

    /** Script Change: stop the scene for a moment. */
    case Pause = 'pause';

    /** Script Change: go back and play the moment differently. */
    case Rewind = 'rewind';

    /** Script Change: skip ahead, past the scene. */
    case FastForward = 'fast-forward';

    /** The open door: the participant leaves the game. */
    case OpenDoor = 'open-door';

    /** The check-in: the game master asks how everyone is doing. */
    case CheckIn = 'check-in';

    /** An answer to the check-in: all good, go on. */
    case AllGood = 'all-good';

    /** An answer to the check-in: unsure, slow down. */
    case Unsure = 'unsure';

    /** An answer to the check-in: not okay, stop. */
    case NotOkay = 'not-okay';

    public function tool(): SafetyTool
    {
        return match ($this) {
            self::XCard => SafetyTool::XCard,
            self::Pause, self::Rewind, self::FastForward => SafetyTool::ScriptChange,
            self::OpenDoor => SafetyTool::OpenDoor,
            self::CheckIn, self::AllGood, self::Unsure, self::NotOkay => SafetyTool::CheckIn,
        };
    }

    /**
     * Whether only the game master raises the signal.
     */
    public function isGameMasters(): bool
    {
        return self::CheckIn === $this;
    }

    /**
     * Whether the signal answers a check-in.
     */
    public function isAnswer(): bool
    {
        return \in_array($this, [self::AllGood, self::Unsure, self::NotOkay], true);
    }
}
