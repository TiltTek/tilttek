<?php

declare(strict_types=1);

namespace Tilttek\Referrals;

/**
 * Why a referral or its reward is refused.
 */
enum Refusal: string
{
    /** The referrer and the newcomer are one member, or the application tells they are one person. */
    case SelfReferral = 'self_referral';

    /** The referrer's account is younger than the seniority the terms ask. */
    case NewMember = 'new_member';

    /** The newcomer joined longer ago than the window the terms allow. */
    case NotNewcomer = 'not_newcomer';

    /** The newcomer was referred by another member. */
    case AlreadyReferred = 'already_referred';

    /** The newcomer has made no first real action at or after the referral. */
    case NoFirstAction = 'no_first_action';
}
