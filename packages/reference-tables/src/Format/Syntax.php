<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Format;

use Tilttek\ReferenceTables\Exception\InvalidDocument;

/**
 * The two syntaxes a document of tables is written in.
 */
enum Syntax: string
{
    case Yaml = 'yaml';
    case Json = 'json';

    /**
     * The syntax of a file by its extension: `.yaml`, `.yml` or `.json`.
     *
     * @throws InvalidDocument
     */
    public static function ofPath(string $path): self
    {
        return match (strtolower(pathinfo($path, \PATHINFO_EXTENSION))) {
            'yaml', 'yml' => self::Yaml,
            'json' => self::Json,
            default => throw InvalidDocument::at('', \sprintf('the file "%s" is neither .yaml, .yml nor .json.', basename($path))),
        };
    }
}
