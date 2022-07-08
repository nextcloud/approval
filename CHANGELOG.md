# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 1.0.10 – 2022-07-08
### Changed
- bump js libs
- use latest @nextcloud/vue
- adjustments for Nextcloud 25 (no more svg api, replace icon classes with material icons etc...)
- polish UI
- CI tests with multiple Nextcloud, DB and Php version

### Fixed
- Sharing is now working with chained rules
  [#27](https://github.com/nextcloud/approval/issues/11) @rmuzzini @osm-frasch @xenophil90

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
