<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

/**
 * How an import treats the copies a collection file no longer names.
 */
enum ImportMode: string
{
    /** The file adds and updates; the copies it no longer names stay as they are. */
    case Add = 'add';

    /**
     * The file adds and updates, and the imported copies it no longer names move to previously owned; the copies the
     * member entered by hand are never touched.
     */
    case Synchronize = 'synchronize';
}
