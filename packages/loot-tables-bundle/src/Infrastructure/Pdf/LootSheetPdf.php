<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Infrastructure\Pdf;

use DateTimeImmutable;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;
use Tilttek\LootTablesBundle\Application\LootPrint;
use Tilttek\LootTablesBundle\Application\PrintedDraw;
use Tilttek\LootTablesBundle\Application\PrintState;

/**
 * The production sheet of an order's draws, as a PDF: for each draw, as `LootPrint` composes it from `LootResults`, the
 * line and the option it draws and its unit, then the entry of each table with its face and its slot — or the text drawn —, the shop code,
 * and whether the draw is verified against its commitment, entered from physical dice, or not drawn yet. Whoever
 * prints the order reads from it what goes on each unit.
 *
 * The labels are English by default; an application printing in another language passes its own. Helvetica prints the
 * Western languages; an application whose entries need any other script passes a TrueType font and its bold.
 */
final readonly class LootSheetPdf
{
    public const array LABELS = [
        'title' => 'Loot of order %s',
        'draw' => '%s, %s, unit %d',
        'entry' => '%s: %s (%d)',
        'slot' => '%s, %s',
        'text' => 'Text: %s',
        'shop_code' => 'Shop code: %s',
        'verified' => 'Verified against its commitment',
        'not_verified' => 'Does not check out against its commitment',
        'physical' => 'Physical dice entered at a register',
        'pending' => 'Not drawn yet',
        'empty' => 'This order has no loot to draw.',
    ];

    private const float MARGIN = 54;

    private const float LINE = 14;

    /** How far the entries of a draw stand in from its heading. */
    private const float INDENT = 12;

    /** @var array<string, string> */
    private array $labels;

    private PageSize $size;

    /**
     * @param array<string, string> $labels `LABELS`' keys, translated; any other is unused
     * @param PageSize|null         $size   the page; Letter when none is given
     */
    public function __construct(
        private LootPrint $print,
        array $labels = [],
        ?PageSize $size = null,
        private StandardFont|TrueTypeFont $font = StandardFont::Helvetica,
        private StandardFont|TrueTypeFont $bold = StandardFont::HelveticaBold,
    ) {
        $this->labels = array_replace(self::LABELS, $labels);
        $this->size = $size ?? PageSize::letter();
    }

    /**
     * @param ?DateTimeImmutable $at when the sheet is printed, written in the document's information
     */
    public function render(string $orderNumber, ?DateTimeImmutable $at = null): string
    {
        $title = \sprintf($this->labels['title'], $orderNumber);
        $pdf = new Document($this->size, new Metadata($title, createdAt: $at));
        $pdf->addPage();

        $y = $this->size->height - self::MARGIN;
        $pdf->text(self::MARGIN, $y, $title, 16, $this->bold);
        $y -= self::LINE;
        $draws = $this->print->compose($orderNumber);

        if ([] === $draws) {
            $this->write($pdf, $y, $this->labels['empty'], 0, $this->font);
        }

        foreach ($draws as $printed) {
            $draw = $printed->draw;
            $y -= self::LINE / 2;
            $y = $this->write($pdf, $y, \sprintf($this->labels['draw'], $draw->line(), $draw->option(), $draw->unit()), 0, $this->bold);

            foreach ($this->lines($printed) as $line) {
                $y = $this->write($pdf, $y, $line, self::INDENT, $this->font);
            }
        }

        return $pdf->render();
    }

    /**
     * What is printed below the heading of a draw.
     *
     * @return list<string>
     */
    private function lines(PrintedDraw $printed): array
    {
        if (PrintState::Pending === $printed->state) {
            return [$this->labels['pending']];
        }

        $text = $printed->text();
        $lines = null === $text ? [] : [\sprintf($this->labels['text'], $text)];

        foreach ($printed->picks() as $pick) {
            $entry = null === $pick->entry->slot ? $pick->entry->name : \sprintf($this->labels['slot'], $pick->entry->name, $pick->entry->slot);
            $lines[] = \sprintf($this->labels['entry'], $pick->table->name, $entry, $pick->face);
        }

        if (null !== $printed->shopCode) {
            $lines[] = \sprintf($this->labels['shop_code'], $printed->shopCode);
        }

        $lines[] = match ($printed->state) {
            PrintState::Physical => $this->labels['physical'],
            PrintState::Verified => $this->labels['verified'],
            PrintState::NotVerified => $this->labels['not_verified'],
        };

        return $lines;
    }

    /**
     * Writes a text wrapped to the width of the page, on a new page when this one is full, and gives the baseline of
     * the line after it.
     */
    private function write(Document $pdf, float $y, string $text, float $indent, StandardFont|TrueTypeFont $font): float
    {
        foreach (Document::wrap($text, $this->size->width - 2 * self::MARGIN - $indent, 10, $font) as $line) {
            $y -= self::LINE;

            if ($y < self::MARGIN) {
                $pdf->addPage();
                $y = $this->size->height - self::MARGIN;
            }

            $pdf->text(self::MARGIN + $indent, $y, $line, 10, $font);
        }

        return $y;
    }
}
