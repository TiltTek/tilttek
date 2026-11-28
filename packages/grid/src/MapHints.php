<?php

declare(strict_types=1);

namespace Tilttek\Grid;

use Tilttek\Grid\Exception\OutOfBounds;

/**
 * What the file name of a map image says of its grid, as map makers write it — `forest_30x20_140ppi.jpg`,
 * `cave [25x30] 300dpi.png`: the pixels a cell spans and the number of columns and rows, each one or none.
 */
final readonly class MapHints
{
    /**
     * The fewest and the most pixels per cell read from a name.
     */
    public const int MIN_PIXELS = 10;

    public const int MAX_PIXELS = 2000;

    /**
     * The most columns or rows read from a name.
     */
    public const int MAX_CELLS = 999;

    /**
     * The longest name read; a longer one gives no hint.
     */
    public const int MAX_LENGTH = 255;

    private const string PIXELS = '/(?<![0-9])([0-9]{2,4})[ _.-]?(?:dpi|ppi|pxpi|px)(?![a-z])/i';

    private const string CELLS = '/(?<![0-9])([0-9]{1,3}) ?[x×] ?([0-9]{1,3})(?![0-9])/iu';

    /**
     * @param int|null $pixelsPerCell the pixels a cell spans, {@see self::MIN_PIXELS} to {@see self::MAX_PIXELS}
     * @param int|null $columns       the columns of the map, 1 to {@see self::MAX_CELLS}
     * @param int|null $rows          the rows of the map, 1 to {@see self::MAX_CELLS}
     */
    private function __construct(public ?int $pixelsPerCell, public ?int $columns, public ?int $rows)
    {
    }

    /**
     * Reads the hints of a file name or a path; its directories are left out. A name that says nothing, or says it
     * out of bounds, gives no hint rather than an error.
     */
    public static function fromFileName(string $path): self
    {
        $name = basename(str_replace('\\', '/', $path));
        if (\strlen($name) > self::MAX_LENGTH) {
            return new self(null, null, null);
        }

        $pixels = null;
        if (1 === preg_match(self::PIXELS, $name, $match)) {
            $value = (int) $match[1];
            $pixels = $value >= self::MIN_PIXELS && $value <= self::MAX_PIXELS ? $value : null;
        }

        $columns = null;
        $rows = null;
        if (1 === preg_match(self::CELLS, $name, $match)) {
            [$columns, $rows] = [(int) $match[1], (int) $match[2]];
            if (0 === $columns || 0 === $rows) {
                [$columns, $rows] = [null, null];
            }
        }

        return new self($pixels, $columns, $rows);
    }

    /**
     * Whether the name gave no hint at all.
     */
    public function isEmpty(): bool
    {
        return null === $this->pixelsPerCell && null === $this->columns;
    }

    /**
     * The width of a cell for an image of the width given: the pixels per cell when the name gave them, otherwise the
     * width of the image shared by its columns; null when the name gave neither.
     *
     * @throws OutOfBounds when the image width is not positive
     */
    public function cellWidth(int $imageWidth): ?Ratio
    {
        if ($imageWidth < 1) {
            throw OutOfBounds::because('An image is at least one pixel wide.');
        }

        if (null !== $this->pixelsPerCell) {
            return Ratio::of($this->pixelsPerCell);
        }

        return null === $this->columns ? null : Ratio::of($imageWidth, $this->columns);
    }
}
