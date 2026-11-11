<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Tilttek\Quests\Exception\InvalidQuest;

/**
 * A reward a quest declares, never executes: its kind — `points`, `badge`, `item`, or any the application branches —,
 * what it names in that kind — a currency, a badge's code, an item's reference — and how many. The application gives
 * it when the quest is accomplished, through the packages it has.
 */
final readonly class Reward
{
    public const int MAX_CODE_LENGTH = 255;

    public const int MAX_AMOUNT = 1_000_000_000;

    public function __construct(
        public string $kind,
        public string $code,
        public int $amount = 1,
    ) {
        Names::check($kind, 'reward kind');

        if ('' === $code || \strlen($code) > self::MAX_CODE_LENGTH || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $code)) {
            throw InvalidQuest::because(\sprintf('What a reward names is 1 to %d bytes without whitespace or control characters; "%s" is not.', self::MAX_CODE_LENGTH, $code));
        }

        if ($amount < 1 || $amount > self::MAX_AMOUNT) {
            throw InvalidQuest::because(\sprintf('A reward gives 1 to %d; this one gives %d.', self::MAX_AMOUNT, $amount));
        }
    }

    /**
     * @return array{kind: string, code: string, amount: int}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'code' => $this->code, 'amount' => $this->amount];
    }
}
