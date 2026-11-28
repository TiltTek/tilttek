<?php

declare(strict_types=1);

namespace Tilttek\TableRules;

use Tilttek\TableRules\Exception\InvalidRules;
use Tilttek\TableRules\Internal\Identifier;
use Tilttek\TableRules\Internal\Text;

/**
 * One house rule of a table: its identifier, kept from one version to the next so that its changes can be shown, a
 * title, its text and an optional category. The text is plain text, which the application renders as it likes; its
 * line breaks are line feeds, its lines without trailing blanks.
 */
final readonly class Rule
{
    /** The most characters of a title. */
    public const int MAX_TITLE = 200;

    /** The most characters of a text. */
    public const int MAX_TEXT = 10_000;

    /** The most lines of a text. */
    public const int MAX_LINES = 100;

    public string $title;

    public string $text;

    /**
     * @param string  $id       the identifier of the rule, the same in every version
     * @param ?string $category the identifier of a category the application names: "safety", "table-talk", "dice"...
     *
     * @throws InvalidRules when an identifier is not one, or the title or the text is empty, too long or not plain text
     */
    public function __construct(public string $id, string $title, string $text, public ?string $category = null)
    {
        Identifier::check($id, 'a rule');
        if (null !== $category) {
            Identifier::check($category, 'a category');
        }

        $this->title = Text::line($title, self::MAX_TITLE, 'the title of a rule');
        $this->text = Text::block($text, self::MAX_TEXT, self::MAX_LINES, 'the text of a rule');
    }

    /**
     * Whether the other rule has the same identifier, title, text and category.
     */
    public function equals(self $other): bool
    {
        return $this->id === $other->id
            && $this->title === $other->title
            && $this->text === $other->text
            && $this->category === $other->category;
    }

    /**
     * The lines of the text.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return explode("\n", $this->text);
    }

    /**
     * @return array{id: string, title: string, text: string, category: ?string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'text' => $this->text, 'category' => $this->category];
    }
}
