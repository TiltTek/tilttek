# Exceptions

Every exception of the library implements `Tilttek\RpgSystems\Exception\RpgSystemsException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidRecord` | A key, a locale, a text, a provenance, an attribute's bounds, a dice expression, a system or a profile is refused when it is made | `The attribute "grit" has a minimum, 7, above its maximum, 6.` |
| `InvalidDocument` | A document, an extract or a data package cannot be read — a document out of the package or changed after its manifest, an installed package without a directory or whose manifest declares another name included —: not YAML or JSON, too long, a field unknown or of the wrong type, a licence refused; the message names the place | `attributes[0].minimum: a whole number is expected.` |
| `UnknownRecord` | A system, an attribute, a dice expression or a term is asked for by a key none has, a localisation names one, or a data package that is not installed is loaded | `The system "lanterns" has no term with the key "range.far".` |

`Package\DataPackageWriter::write()` raises PHP's own `UnexpectedValueException` when its directory does not exist or a
file cannot be written.

A record refused while a document is read is given back as an `InvalidDocument` with its place, the `InvalidRecord`
as its previous exception.
