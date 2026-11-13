<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\SessionZero\Template;
use Tilttek\TableSafety\SessionZero\Templates;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Signal\SignalKind;

/**
 * What a table chose: its tools, whether its signals are named, who sees the X-card and Script Change, the themes it
 * adds to the catalogue, and its session zero.
 *
 * By default a table uses every tool, its signals are anonymous and seen by the whole table, it adds no theme, and its
 * session zero is the built-in campaign template.
 */
final readonly class TableSettings
{
    /** @var list<SafetyTool> in the order of `SafetyTool::cases()` */
    public array $tools;

    public Template $sessionZero;

    /**
     * @param list<SafetyTool>      $tools         the tools the table uses
     * @param bool                  $namedSignals  whether a signal says who raised it; never for an answer to a check-in
     * @param Audience              $signalsSeenBy who sees the X-card and Script Change: the table, or the game master alone
     * @param array<string, string> $customThemes  the label of each theme the table adds, by key
     */
    public function __construct(
        ?array $tools = null,
        public bool $namedSignals = false,
        public Audience $signalsSeenBy = Audience::Table,
        public array $customThemes = [],
        ?Template $sessionZero = null,
    ) {
        $this->tools = array_values(array_filter(SafetyTool::cases(), static fn (SafetyTool $tool): bool => null === $tools || \in_array($tool, $tools, true)));
        $this->sessionZero = $sessionZero ?? Templates::get(Templates::DEFAULT);

        if (\count($customThemes) > TableThemes::MAX_CUSTOM) {
            throw InvalidAnswer::because(\sprintf('A table adds at most %d themes.', TableThemes::MAX_CUSTOM));
        }
    }

    public static function defaults(): self
    {
        return new self();
    }

    public function uses(SafetyTool $tool): bool
    {
        return \in_array($tool, $this->tools, true);
    }

    /**
     * Who sees a signal of a kind: the game master alone for the open door and the answers to a check-in, the table for
     * a check-in, and the choice of the table for the X-card and Script Change.
     */
    public function audienceOf(SignalKind $kind): Audience
    {
        return match (true) {
            SignalKind::OpenDoor === $kind, $kind->isAnswer() => Audience::GameMaster,
            SignalKind::CheckIn === $kind => Audience::Table,
            default => $this->signalsSeenBy,
        };
    }

    /**
     * Whether a signal of a kind says who raised it: only at a table that chose named signals, and never an answer to
     * a check-in or the game master's own check-in.
     */
    public function names(SignalKind $kind): bool
    {
        return $this->namedSignals && !$kind->isAnswer() && !$kind->isGameMasters();
    }

    /**
     * @param list<SafetyTool> $tools
     */
    public function withTools(array $tools): self
    {
        return new self($tools, $this->namedSignals, $this->signalsSeenBy, $this->customThemes, $this->sessionZero);
    }

    public function withNamedSignals(bool $named): self
    {
        return new self($this->tools, $named, $this->signalsSeenBy, $this->customThemes, $this->sessionZero);
    }

    public function withSignalsSeenBy(Audience $audience): self
    {
        return new self($this->tools, $this->namedSignals, $audience, $this->customThemes, $this->sessionZero);
    }

    /**
     * @param array<string, string> $themes
     */
    public function withCustomThemes(array $themes): self
    {
        return new self($this->tools, $this->namedSignals, $this->signalsSeenBy, $themes, $this->sessionZero);
    }

    public function withSessionZero(Template $template): self
    {
        return new self($this->tools, $this->namedSignals, $this->signalsSeenBy, $this->customThemes, $template);
    }

    /**
     * @return array{tools: list<string>, named_signals: bool, signals_seen_by: string, custom_themes: array<string, string>, session_zero: array{key: string, questions: list<array{key: string, kind: string, prompt: ?string, options: array<array-key, ?string>, required: bool}>}}
     */
    public function toArray(): array
    {
        return [
            'tools' => array_map(static fn (SafetyTool $tool): string => $tool->value, $this->tools),
            'named_signals' => $this->namedSignals,
            'signals_seen_by' => $this->signalsSeenBy->value,
            'custom_themes' => $this->customThemes,
            'session_zero' => $this->sessionZero->toArray(),
        ];
    }

    /**
     * @param array<array-key, mixed> $stored what `toArray()` returned
     */
    public static function fromArray(array $stored): self
    {
        $tools = [];

        foreach (\is_array($stored['tools'] ?? null) ? $stored['tools'] : [] as $tool) {
            $tools[] = SafetyTool::tryFrom(\is_string($tool) ? $tool : '') ?? throw InvalidAnswer::because('A stored tool is unknown.');
        }

        $themes = [];

        foreach (\is_array($stored['custom_themes'] ?? null) ? $stored['custom_themes'] : [] as $key => $label) {
            $themes[(string) $key] = \is_string($label) ? $label : throw InvalidAnswer::because('A stored theme has no label.');
        }

        return new self(
            $tools,
            true === ($stored['named_signals'] ?? false),
            Audience::tryFrom(\is_string($stored['signals_seen_by'] ?? null) ? $stored['signals_seen_by'] : '') ?? Audience::Table,
            $themes,
            \is_array($stored['session_zero'] ?? null) ? Template::fromArray($stored['session_zero']) : null,
        );
    }
}
