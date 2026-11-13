<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\SessionZero;

use Tilttek\TableSafety\Exception\InvalidAnswer;

/**
 * The built-in session zero templates a game master starts from: a one-shot, a campaign, a convention table and an
 * online table. Their questions have no prompt: the application translates each from its key, as
 * `session_zero.questions.<key>` and `session_zero.options.<question>.<option>`.
 */
final class Templates
{
    public const string ONE_SHOT = 'one-shot';

    public const string CAMPAIGN = 'campaign';

    public const string CONVENTION = 'convention';

    public const string ONLINE = 'online';

    /** The template of a table that names none. */
    public const string DEFAULT = self::CAMPAIGN;

    private const array TEMPLATES = [
        self::ONE_SHOT => ['tone', 'pillars', 'themes-welcome', 'player-conflict', 'character-death', 'comfort'],
        self::CAMPAIGN => ['tone', 'pillars', 'expectations', 'themes-welcome', 'player-conflict', 'character-death', 'frequency', 'schedule', 'table-rules', 'comfort'],
        self::CONVENTION => ['experience', 'tone', 'player-conflict', 'comfort'],
        self::ONLINE => ['tone', 'pillars', 'themes-welcome', 'camera', 'recording', 'table-rules', 'comfort'],
    ];

    private function __construct()
    {
    }

    /**
     * @return list<string> the keys of the built-in templates
     */
    public static function keys(): array
    {
        return array_keys(self::TEMPLATES);
    }

    public static function has(string $key): bool
    {
        return isset(self::TEMPLATES[$key]);
    }

    public static function get(string $key): Template
    {
        $questions = self::TEMPLATES[$key] ?? throw InvalidAnswer::because(\sprintf('There is no built-in template "%s".', InvalidAnswer::excerpt($key)));

        return new Template($key, array_map(self::question(...), $questions));
    }

    /**
     * @return array<string, Template> every built-in template, by key
     */
    public static function all(): array
    {
        return array_combine(self::keys(), array_map(self::get(...), self::keys()));
    }

    private static function question(string $key): Question
    {
        $choice = static fn (string ...$options): array => array_fill_keys($options, null);

        return match ($key) {
            'tone' => new Question($key, QuestionKind::Choice, options: $choice('light', 'mixed', 'serious', 'dark'), required: true),
            'pillars' => new Question($key, QuestionKind::Choices, options: $choice('combat', 'exploration', 'social', 'mystery', 'drama')),
            'player-conflict' => new Question($key, QuestionKind::Choice, options: $choice('never', 'with-consent', 'welcome')),
            'character-death' => new Question($key, QuestionKind::Choice, options: $choice('never', 'rare', 'possible')),
            'frequency' => new Question($key, QuestionKind::Choice, options: $choice('weekly', 'fortnightly', 'monthly')),
            'experience' => new Question($key, QuestionKind::Choice, options: $choice('new', 'some', 'seasoned')),
            'camera' => new Question($key, QuestionKind::Choice, options: $choice('video', 'voice', 'text')),
            'recording' => new Question($key, QuestionKind::Choice, options: $choice('never', 'ask-first', 'allowed'), required: true),
            default => new Question($key, QuestionKind::Text),
        };
    }
}
