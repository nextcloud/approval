# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

### Changed
- bump dependencies

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
