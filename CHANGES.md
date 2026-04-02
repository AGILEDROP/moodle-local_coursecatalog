# Changelog

## Version 1.2.0 (2026033100)
- Add pagination for course listings with configurable items per page (default: 6)
- Add application-level caching for course card data with automatic invalidation on course, section, module, and category changes
- Add selective subcategory inclusion via autocomplete multi-select when "Include subcategories" is enabled
- Fix unused JavaScript module for category selection replaced with inline form logic

## Version 1.1.1 (2026031601)
- Add explicit catalog page ordering via sortorder field with move up/down controls
- Add manager class to encapsulate catalog page business logic
- Add rich text page description handling via pagedescription_editor
- Improve accessibility for grid/list view modes
- Refine guest access logic and view capability checks
- General code cleanup and formatting improvements

## Version 1.1.0 (2026030800)
- Add grid and list view toggle for course listings
- Add guest access functionality for unauthenticated users
- Add primary navigation support for catalog pages
- Add automatic orphan cleanup when linked course categories are deleted
- Add GitHub Actions CI workflow for testing across multiple PHP versions, databases, and Moodle branches
