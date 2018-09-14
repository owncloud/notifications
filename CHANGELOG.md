# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [0.3.5]

- Added translations - [#233](https://github.com/owncloud/notifications/issues/233)
- Clear user notifications after user deletion - [#223](https://github.com/owncloud/notifications/issues/223) [#238](https://github.com/owncloud/notifications/issues/238)
- Include the product name in the mail for the notifications - [#211](https://github.com/owncloud/notifications/issues/211)
- Add "hello" in the mail templates - [#209](https://github.com/owncloud/notifications/issues/209)
- Add common footer to notifications - [#207](https://github.com/owncloud/notifications/issues/207)

## [0.3.4]

- Fix migration from 8.2.11 to 10.0.x with postgresql - [#195](https://github.com/owncloud/notifications/issues/195)
- Adjust email message for notifications to be more user-friendly - [#188](https://github.com/owncloud/notifications/issues/188)

## [0.3.3]

- Allow CORS requests to list notifications - [#176](https://github.com/owncloud/notifications/issues/176)
- Include the template folder in the Makefile - [#168](https://github.com/owncloud/notifications/issues/168)
- Add support for email notifications - [#156](https://github.com/owncloud/notifications/issues/156) [#162](https://github.com/owncloud/notifications/issues/162) [#175](https://github.com/owncloud/notifications/issues/175) [#171](https://github.com/owncloud/notifications/issues/171)
- Add occ command arguments for link and link text - [#172](https://github.com/owncloud/notifications/issues/172)
- Fix occ command for group notification - [#146](https://github.com/owncloud/notifications/issues/146)

## [0.3.2]
### Fixed
- Fix login page URL detection which caused trouble with shibboleth users - [#122](https://github.com/owncloud/notifications/issues/122)

## [0.3.1]
### Added
- Notifications can now have an icon - [#104](https://github.com/owncloud/notifications/issues/104)
- Added occ command to send notification to a user or a grouop - [#104](https://github.com/owncloud/notifications/issues/104)

### Fixed
- Make sure buttons stays in place even with long messages - [#114](https://github.com/owncloud/notifications/issues/114)
- Don't escape link text title - [#111](https://github.com/owncloud/notifications/issues/111)
- Fix actions and escaping - [#109](https://github.com/owncloud/notifications/issues/109)
- Move OCS calls to app framework - consumes less resources - [#98](https://github.com/owncloud/notifications/pull/98)
- Don't use escaped message for browser notification - [#100](https://github.com/owncloud/notifications/pull/100)

[0.3.5]: https://github.com/owncloud/notifications/compare/v10.0.9...stable10
[0.3.4]: https://github.com/owncloud/notifications/compare/v10.0.8...v10.0.9
[0.3.3]: https://github.com/owncloud/notifications/compare/v10.0.4...v10.0.8
[0.3.2]: https://github.com/owncloud/notifications/compare/v10.0.3...v10.0.4
[0.3.1]: https://github.com/owncloud/notifications/compare/v10.0.2...v10.0.4RC2

