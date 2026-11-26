<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

/**
 * Why an act on a handout is refused.
 */
enum Refusal: string
{
    case AlreadyCreated = 'already_created';

    case TooManyHandouts = 'too_many_handouts';

    case TooManyAttachments = 'too_many_attachments';

    case MediaTypeNotAllowed = 'media_type_not_allowed';

    case AudienceTooLarge = 'audience_too_large';

    case TooManyRevealed = 'too_many_revealed';

    case TooManyStaged = 'too_many_staged';

    case NotRevealed = 'not_revealed';
}
