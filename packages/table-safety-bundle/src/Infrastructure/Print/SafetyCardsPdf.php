<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Print;

use DateTimeImmutable;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;
use Tilttek\TableSafety\SafetyTool;

/**
 * The printable cards of a table, as a PDF written by `phil/paged-media`: the X-card, a large X to lay on the table;
 * the sheet of the lines and veils, a box for a line and one for a veil beside each theme; and the sheet of the consent
 * checklist, a green, a yellow and a red box beside each theme. The sheets are blank: a participant fills theirs by
 * hand, and nothing of anyone's answers is printed.
 *
 * The labels are English by default; the screens pass them translated. Helvetica prints the Western languages; an
 * application whose themes need any other script passes a TrueType font and its bold.
 */
final readonly class SafetyCardsPdf
{
    public const array LABELS = [
        'title' => 'Printable cards',
        'x_card' => 'X-card',
        'x_card_help' => 'Touch or raise this card to take out what just happened. Nobody asks why.',
        'lines_and_veils' => 'Lines and veils',
        'lines_and_veils_help' => 'A line keeps a theme out of the game; a veil lets it happen off screen. Leave both boxes empty for an open theme.',
        'consent' => 'Consent checklist',
        'consent_help' => 'Green, welcome; yellow, with care; red, not at this table. Leave the boxes empty for a theme you do not answer.',
        'table_themes' => "This table's themes",
        'theme' => 'Theme',
        'line' => 'Line',
        'veil' => 'Veil',
        'green' => 'Green',
        'yellow' => 'Yellow',
        'red' => 'Red',
    ];

    private const float MARGIN = 54;

    private const float LINE = 18;

    private const float BOX = 11;

    private const float COLUMN = 54;

    /** @var array<string, string> */
    private array $labels;

    private PageSize $size;

    /**
     * @param array<string, string> $labels `LABELS`' keys, translated; any other is unused
     * @param PageSize|null         $size   the page; Letter when none is given
     */
    public function __construct(
        array $labels = [],
        ?PageSize $size = null,
        private StandardFont|TrueTypeFont $font = StandardFont::Helvetica,
        private StandardFont|TrueTypeFont $bold = StandardFont::HelveticaBold,
    ) {
        $this->labels = array_replace(self::LABELS, $labels);
        $this->size = $size ?? PageSize::letter();
    }

    /**
     * @param list<SafetyTool>                                     $tools  the tools to print, in order; a tool without a card is left out
     * @param list<array{category: ?string, themes: list<string>}> $groups the themes by category, the table's own last
     * @param array<string, string>                                $labels `LABELS`' keys, translated, over those of the service
     * @param ?DateTimeImmutable                                   $at     when the cards are printed, written in the document's information
     */
    public function render(array $tools, array $groups, array $labels = [], ?DateTimeImmutable $at = null): string
    {
        $labels = array_replace($this->labels, $labels);
        $pdf = new Document($this->size, new Metadata($labels['title'], createdAt: $at));

        foreach ($tools as $tool) {
            match ($tool) {
                SafetyTool::XCard => $this->xCard($pdf, $labels),
                SafetyTool::LinesAndVeils => $this->sheet($pdf, $labels, 'lines_and_veils', ['line', 'veil'], $groups),
                SafetyTool::ConsentChecklist => $this->sheet($pdf, $labels, 'consent', ['green', 'yellow', 'red'], $groups),
                default => null,
            };
        }

        if (0 === $pdf->pageCount()) {
            $pdf->addPage();
        }

        return $pdf->render();
    }

    /**
     * @param array<string, string> $labels
     */
    private function xCard(Document $pdf, array $labels): void
    {
        $pdf->addPage();
        $width = $this->size->width - 2 * self::MARGIN;
        $side = min($width, $this->size->height - 4 * self::MARGIN);
        $left = self::MARGIN + ($width - $side) / 2;
        $bottom = $this->size->height - self::MARGIN - $side;

        $pdf->rectangle($left, $bottom, $side, $side, stroke: Color::black(), lineWidth: 4);
        $inset = $side / 6;
        $pdf->line($left + $inset, $bottom + $inset, $left + $side - $inset, $bottom + $side - $inset, $side / 12);
        $pdf->line($left + $inset, $bottom + $side - $inset, $left + $side - $inset, $bottom + $inset, $side / 12);

        $y = $bottom - 2 * self::LINE;
        $pdf->text(self::MARGIN, $y, $labels['x_card'], 18, $this->bold);
        $this->paragraph($pdf, $y - self::LINE, $labels['x_card_help']);
    }

    /**
     * A sheet of themes with a box per answer beside each.
     *
     * @param array<string, string>                                $labels
     * @param list<string>                                         $columns the keys of the labels of the boxes
     * @param list<array{category: ?string, themes: list<string>}> $groups
     */
    private function sheet(Document $pdf, array $labels, string $title, array $columns, array $groups): void
    {
        $pdf->addPage();
        $y = $this->size->height - self::MARGIN;
        $pdf->text(self::MARGIN, $y, $labels[$title], 18, $this->bold);
        $y = $this->paragraph($pdf, $y - self::LINE, $labels[$title.'_help']) - self::LINE;
        $y = $this->header($pdf, $y, $labels, $columns);

        foreach ($groups as $group) {
            $y = $this->room($pdf, $y, 2, $labels, $columns);
            $pdf->text(self::MARGIN, $y, $group['category'] ?? $labels['table_themes'], 11, $this->bold);
            $y -= self::LINE;

            foreach ($group['themes'] as $theme) {
                $y = $this->room($pdf, $y, 1, $labels, $columns);
                $pdf->text(self::MARGIN + self::BOX, $y, $theme, 10, $this->font);

                foreach (array_keys($columns) as $index) {
                    $pdf->rectangle($this->column($index, \count($columns)) - self::BOX / 2, $y - 2, self::BOX, self::BOX);
                }

                $y -= self::LINE;
            }
        }
    }

    /**
     * The labels of the boxes, above their columns, and the baseline of the line below them.
     *
     * @param array<string, string> $labels
     * @param list<string>          $columns
     */
    private function header(Document $pdf, float $y, array $labels, array $columns): float
    {
        $pdf->text(self::MARGIN, $y, $labels['theme'], 10, $this->bold);

        foreach ($columns as $index => $column) {
            $label = $labels[$column];
            $pdf->text($this->column($index, \count($columns)) - $this->bold->width($label, 10) / 2, $y, $label, 10, $this->bold);
        }

        $pdf->line(self::MARGIN, $y - 6, $this->size->width - self::MARGIN, $y - 6, 0.5);

        return $y - self::LINE - 4;
    }

    /**
     * The baseline to write at: this one when the page holds the lines asked for, else the first of a new page under
     * the labels of the boxes again.
     *
     * @param array<string, string> $labels
     * @param list<string>          $columns
     */
    private function room(Document $pdf, float $y, int $lines, array $labels, array $columns): float
    {
        if ($y - ($lines - 1) * self::LINE >= self::MARGIN) {
            return $y;
        }

        $pdf->addPage();

        return $this->header($pdf, $this->size->height - self::MARGIN, $labels, $columns);
    }

    /**
     * The middle of a column of boxes: the columns stand side by side at the right of the page.
     */
    private function column(int $index, int $count): float
    {
        return $this->size->width - self::MARGIN - ($count - $index - 0.5) * self::COLUMN;
    }

    /**
     * Writes a text wrapped to the width of the page, and gives the baseline of its last line.
     */
    private function paragraph(Document $pdf, float $y, string $text): float
    {
        $lines = Document::wrap($text, $this->size->width - 2 * self::MARGIN, 11, $this->font);

        foreach ($lines as $index => $line) {
            $pdf->text(self::MARGIN, $y - $index * 15, $line, 11, $this->font);
        }

        return $y - (\count($lines) - 1) * 15;
    }
}
