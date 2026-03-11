# Local Course Catalog

Local Course Catalog is a Moodle local plugin for creating category-based catalog landing pages with a clean, manageable admin workflow. It lets administrators define custom catalog pages, control visibility, and optionally expose selected pages in the primary navigation.

Ideal for site home pages, program hubs, and curated category pages, it helps present visible courses from chosen categories in a consistent card-based layout.

## Key Features

- Category Catalog Pages: Create custom catalog pages mapped to a single Moodle course category.
- Slug-Based URLs: Each page has a unique URL slug for direct access.
- Grid + List Views: Displays visible category courses in card-based grid or list layouts.
- Page Description Support: Add rich text descriptions per catalog page.
- Course Count Label: Header label is based on the number of visible courses only.
- Sorting Controls: Sort listed courses by name (A-Z / Z-A) or content count (few to many / many to few).
- Visibility Toggles: Enable or disable each catalog page without deleting configuration.
- Primary Navigation Toggle: Show enabled pages directly in Moodle primary navigation.
- Guest Access Toggle: Allow unauthenticated (guest) users to view specific pages without logging in.
- Manager Preview Mode: Managers can preview disabled pages with a warning notice.
- Orphan Cleanup: Automatically removes catalog-page rows when linked course categories are deleted.
- Accessible, Moodle-Native Output: Uses Moodle rendering, templates, and capability checks.

## Requirements

- Moodle 4.5 or higher
- PHP 8.1 or higher
- No additional plugin dependencies

## Installation

### Installing via uploaded ZIP file

1. Log in as an admin and go to `Site administration > Plugins > Install plugins`.
2. Upload the ZIP file with the plugin code.
3. Check the plugin validation report and finish the installation.

### Installing manually

Copy the plugin directory to:

`{your/moodle/dirroot}/local/coursecatalog`

Then visit `Site administration > Notifications` to complete installation.
Alternatively, run:

```bash
php admin/cli/upgrade.php
```

## Usage

### Managing catalog pages

1. Go to `Site administration > Plugins > Local plugins > Course catalog`.
2. Add a new catalog page.
3. Configure:
   - Page name
   - URL slug
   - Page description
   - Course category
4. Save the page and use action buttons to:
   - View page
   - Enable/disable page
   - Show/hide in primary navigation
   - Enable/disable guest access
   - Edit
   - Delete

### Front-end page URL

Catalog pages are served at:

`/local/coursecatalog/view.php?slug=your-slug`

### Behavior notes

- Disabled pages are hidden from regular users.
- Managers can still access disabled pages in preview mode.
- Header count shows visible-course totals.
- Navigation links are only shown when both:
  - Page is enabled
  - "Show in primary navigation" is enabled
- Navigation links are hidden from unauthenticated and guest users unless "Guest access" is also enabled for that page.
- Navigation and guest access cannot be enabled for disabled pages.
- When guest access is enabled, unauthenticated visitors can view the page without logging in.

## Notes

- The plugin stores page configuration in `{local_coursecatalog}`.
- `slug` is unique and used as page lookup key.
- `course_category` is indexed for efficient category-based filtering.
- Linked rows are auto-removed when a course category is deleted.
- If UI or navigation updates do not appear immediately, purge Moodle caches.

## Privacy

This plugin does not store personal data. It stores only catalog page configuration metadata and reads course/category data from Moodle core tables. It implements the Moodle Privacy API as a null provider.

## License

2026 Agiledrop ltd. <developer@agiledrop.com>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program. If not, see https://www.gnu.org/licenses/.
