# Changelog

All notable changes to `tilttek/accessible-dice` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

### Changed

- `tilttek/dice-bundle` 0.2 accepted as well as 0.1.

## 0.1.0-alpha - 2026-11-12

### Added

- `TilttekAccessibleDiceBundle` and its configuration: the standard dice offered, the custom dice offered, how a result
  is announced — politely by default —, and the translation domain of the labels of the faces; `TilttekDiceBundle`
  required, whose custom dice and limits the form uses.
- `AccessibleDice`: the dice offered as `DieChoice`s, standard by their sides and custom by their name, and the
  expression of a choice — a die among those, from one to the dice limit of them, and a modifier from -1000 to 1000 —,
  `InvalidChoice` for any other.
- `FaceDescriber` and `FaceDescription`: each face of a custom die by its label, then its shape and its value in words;
  the labels of every face for the controller.
- `RollAnnouncer`: a roll in one sentence, every die by its label or value, a die that did not count said so, then its
  total or what it gave, the roller's name first when given; `Words`, through the translator or in English;
  `Politeness`.
- Twig: `accessible_dice()`, `accessible_dice_announcement()` and `accessible_dice_faces()`; the form, its dice as
  large choices, the faces of the custom dice described, how many and the modifier with buttons on each side, the
  expression they make, an opt-in to hear the results, and a live region; translations in English and French.
- The Stimulus controller: the expression shown as the choice changes, how many dice stepped, the result announced
  in the live region, each roll of a dice tower on the same page announced, reading aloud only when asked, and the roll
  moving only without a request for reduced motion; its stylesheet, with targets of 48 pixels at least and the shapes
  of the faces.
- A browser suite with Panther, run apart.
- Documentation: installation, configuration, the form, accessibility, extension points, testing and reference.
- Community files: contribution guide, code of conduct, security policy and MIT licence.
