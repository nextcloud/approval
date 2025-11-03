<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.6.0 - 2025-11-03

### Added

- Support for nextcloud 33 and migrate away from IConfig @lukasdotcom [#338](https://github.com/nextcloud/approval/pull/338)

### Fixed

- Improve performance of getApprovalState when there are a lot of rules @lukasdotcom [#340](https://github.com/nextcloud/approval/pull/340)
- Store approval and rejection message before changing state of tags @lukasdotcom [#341](https://github.com/nextcloud/approval/pull/341)

## 2.5.0 - 2025-10-08

### Added

- Allow for custom messages when approving or rejecting a file @lukasdotcom [#330](https://github.com/nextcloud/approval/pull/330)

### Changed

- Bump node dependencies @lukasdotcom [#332](https://github.com/nextcloud/approval/pull/332)

### Fixed

- Hide user status correctly in rules @lukasdotcom [#329](https://github.com/nextcloud/approval/pull/329)
- File ownership @lukasdotcom [#334](https://github.com/nextcloud/approval/pull/334)

## 2.4.0 - 2025-08-18

### Added

- Use outlined icons @julien-nc [#318](https://github.com/nextcloud/approval/pull/318)
- Don't share file with group if already shared with the group @lukasdotcom [#320](https://github.com/nextcloud/approval/pull/320)

### Changed

- Create restricted instead of hidden tags for tag creator @lukasdotcom [#319](https://github.com/nextcloud/approval/pull/319)
- Update screenshots @julien-nc [#324](https://github.com/nextcloud/approval/pull/324)

### Fixed

- Unmount was being called on the app instead of the view @julien-nc [#324](https://github.com/nextcloud/approval/pull/324)

## 2.3.0 - 2025-06-26

### Added

- Add an example of a chained workflow in readme @lukasdotcom [#301](https://github.com/nextcloud/approval/pull/301)
- Allow workflow to unapprove files after they are modified @lukasdotcom [#309](https://github.com/nextcloud/approval/pull/309)
- Support delegated settings to allow admin to delegate workflow settings @lukasdotcom [#310](https://github.com/nextcloud/approval/pull/310)

### Changed

- Support Nextcloud 32
- Made the workflow input field wider @lukasdotcom [#303](https://github.com/nextcloud/approval/pull/303)
- Remove the limit of five tags for tag selector @lukasdotcom [#307](https://github.com/nextcloud/approval/pull/307)
- Use vue 3 and vite @lukasdotcom [#314](https://github.com/nextcloud/approval/pull/314)

### Fixed

- Cleanup test bootstrap @come-nc [#292](https://github.com/nextcloud/approval/pull/292)
- Fix translations @lukasdotcom [#304](https://github.com/nextcloud/approval/pull/304)

## 2.2.0 – 2025-03-19

### Added

- REUSE headers and GitHub actions to check @AndyScherzinger [#253](https://github.com/nextcloud/approval/pull/253)

### Changed

- Add link to activity rich parameter for file/folder @julien-nc [#269](https://github.com/nextcloud/approval/pull/269)

### Fixed

- Fix circles/teams support @julien-nc [#270](https://github.com/nextcloud/approval/pull/270)
- Fix activity rich parameter ID @julien-nc [#269](https://github.com/nextcloud/approval/pull/269)

## 2.1.0 – 2025-01-09

### Changed

- Support Nextcloud 31

### Fixed

- Request action icon was black in dark theme @julien-nc [#228](https://github.com/nextcloud/approval/pull/228)
- Notifications: Notifier::prepare() threw \InvalidArgumentException which is deprecated @nickvergessen [#249](https://github.com/nextcloud/approval/pull/249)

## 2.0.0 – 2024-07-23

### Changed

- Support Nextcloud 30 only

### Fixed

- Show message in files tab if it's not possible to request approval and there is no workflow related with the file @julien-nc [#205](https://github.com/nextcloud/approval/pull/205)

## 1.3.0 – 2024-05-29

### Added

- Approval files sidebar tab [#159](https://github.com/nextcloud/approval/pull/159) @julien-nc
- Psalm checks [#145](https://github.com/nextcloud/approval/pull/145) @julien-nc

### Changed

- max NC is now 30

## 1.2.0 – 2023-12-22

### Changed

- reimplement the frontend for Nextcloud >= 28 [#114](https://github.com/nextcloud/approval/pull/114) @julien-nc
- remove docusign/libresign related code

## 1.1.1 – 2023-11-29

### Added

- Added Deprecation changelog [#111](https://github.com/nextcloud/approval/pull/111) @nc-fkl
- Added Deprecation notice - The Approval App discontinues maintenance for Nextcloud versions 28+ [#102](https://github.com/nextcloud/approval/pull/102) @julien-nc

## 1.1.0 – 2023-07-18

### Changed

- Bump minor version digit to still be able to publish for 24 [#72](https://github.com/nextcloud/approval/pull/72) @julien-nc

## 1.0.14 – 2023-07-10

### Changed

- Optimize propfind plugins [#62](https://github.com/nextcloud/approval/pull/62) @icewind1991
- Drop Nextcloud 24 support [#63](https://github.com/nextcloud/approval/pull/63) @skjnldsv
- Avoid changing global NcModal style [#68](https://github.com/nextcloud/approval/pull/68) @julien-nc

## 1.0.13 – 2023-07-06

### Changed

- Bump max Nextcloud version to 28 [#56](https://github.com/nextcloud/approval/pull/56) @julien-nc
- Store encrypted docusign client secret, make sure old stored value gets encrypted [#54](https://github.com/nextcloud/approval/pull/54) @julien-nc

### Fixed

- Adjust screenshots URLs which were outdated [#45](https://github.com/nextcloud/approval/pull/45) @julien-nc

## 1.0.12 – 2022-12-14
### Fixed
- Issue with empty string default value on notNull DB column
  [#42](https://github.com/nextcloud/approval/pull/42) @julien-nc

### Changed
- Improve test workflows
  [#40](https://github.com/nextcloud/approval/pull/40) @skjnldsv

## 1.0.11 – 2022-12-13
### Fixed
- Fix 1.0.10 release missing js files
- Replace deprecated String.prototype.substr()
- Bump js libs, fix file action color in NC >= 25

### Added
- New translations

## 1.0.10 – 2022-07-08
### Changed
- bump js libs
- use latest @nextcloud/vue
- adjustments for Nextcloud 25 (no more svg api, replace icon classes with material icons etc...)
- polish UI
- CI tests with multiple Nextcloud, DB and Php version

### Fixed
- Sharing is now working with chained rules
  [#27](https://github.com/nextcloud/approval/issues/27) @rmuzzini @osm-frasch @xenophil90

## 1.0.9 – 2021-11-15
### Added
- new translations

### Changed
- bump max NC version to 24
- improve release action
- clarify package.json
- remove DB version constraints

### Fixed
- some DB index names being too long because of long table names

## 1.0.7 – 2021-09-16
### Changed
- allow multiple requests in UI
  [#11](https://github.com/nextcloud/approval/issues/11) @meichthys
- in request modal, only show workflows that have not yet been used for the file
  [#11](https://github.com/nextcloud/approval/issues/11) @meichthys

### Fixed
- trigger file sharing, activity and notifications when a request is done via tag assignment
  (manual or automatic)
  [#14](https://github.com/nextcloud/approval/issues/14) @eco-villenet
- don't display state icon for recent files

## 1.0.6 – 2021-08-23
### Changed
- bump dependencies

### Fixed
- don't search for circles in admin settings, it crashes these days
[#13](https://github.com/nextcloud/approval/issues/13) @OkhamG @zydisney @AdnanCivic

## 1.0.4 – 2021-08-01
### Added
* dashboard widget with pending files user can approve
* multiple file selection with approve/reject actions

### Changed
* bump js libs
* allow hot module replacement for fancy devs

## 1.0.3 – 2021-07-13
### Changed
* update LibreSign endpoint @
[#8](https://github.com/nextcloud/approval/pull/8) @vitormattos

### Fixed
* bug when getting state of an element that is not in current directory

## 1.0.2 – 2021-06-28
### Added
* support new Circles management API
* translations
* unit tests
* Sabre plugin to provide approval state in WebDav PROPFIND requests
* OCS routes to allow interaction with external clients

### Changed
* improve signature request form design
* refactor rule service
* Phpstormify
* bump js libs

### Fixed
* Circle icons here and there

## 1.0.0 – 2021-06-04
### Changed
* clear multiselect after selection
* address another design review feedback
* autofocus on multiselect when opening signature request modal

## 1.0.0 – 2021-06-01
### Changed
* address design review feedback

## 0.0.9 – 2021-05-28
### Added
* ability to request signature via DocuSign for all PDF files

## 0.0.8 – 2021-05-25
### Added
* the app
