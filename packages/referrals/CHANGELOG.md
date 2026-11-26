# Changelog

All notable changes to `tilttek/referrals` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Referrals`: a member's random referral code, drawn once and drawn again when the store finds it taken; a newcomer
  referred through it; the reward in points of `tilttek/points` on both sides, never money, a commission or a
  discount.
- The reward paid only once the newcomer has made a first real action at or after the referral, told by
  `Contract\FirstActions`, never at sign-up; `Refusal::NoFirstAction` otherwise.
- The guards of `tilttek/exchange`: no referral of oneself, nor of another account `Contract\SamePerson` tells is the
  same person, asked again at the reward; a referrer's seniority and a newcomer's window, read through the
  `MemberSince` port of `tilttek/exchange`, off until set; one referral per newcomer.
- Points given once: the referral settled in one step of the store, which keeps the amounts paid, and each side's
  movement written under a key built from the referral's random identifier, so that a reward asked again, or by two
  processes at once, pays nothing more.
- The cap of `Terms::$maxRewards` referrals that reward a referrer, ever, held when two newcomers act at once; past
  it, the newcomer still receives their points; `remaining()`.
- `Terms`: the points of each side, the cap, the currency, the issuance account and the guards on dates, with their
  bounds.
- `ReferralCode` in Crockford's base 32, read back as a person copies it; `ReferralId`; `Referral`, `ReferralStatus`,
  `Reward` and `FirstAction`, moments in UTC.
- Reading: `referralOf()` and `referralsOf()`, oldest first, at most 100 at once.
- Erasing a member: their code and pending referrals as a newcomer deleted, `erased:member` in their place in the
  others, so that no referral links them to anyone.
- `Contract\ReferralStore` with `Store\InMemoryReferralStore`, `Contract\NoSamePersonSignal`.
- Exceptions under one `ReferralsException` interface: `ReferralRefused`, `UnknownReferralCode`, `ReferralConflict`
  and `InvalidReferral`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
