=== WPGraphQL IDE ===
Contributors: jasonbahl, joefusco
Tags: headless, decoupled, graphql, devtools
Requires at least: 5.7
Tested up to: 6.5
Stable tag: 2.1.5
Requires PHP: 7.4
License: GPL-3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

GraphQL IDE for WPGraphQL

== Description ==

GraphQL IDE for WPGraphQL

== Installation ==

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==

= 2.1.5 =

### Patch Changes

- cb6eda0: Reorder sidebar menu to always have the IDE first.
- 1f50c93: Fixes issue where custom capability was not being assigned to the administrator role. This now happens on plugin activation.

= 2.1.4 =

### Patch Changes

- 8f6f131: Update license to GPL-3

= 2.1.3 =

### Patch Changes

- 66f7e28: - Remove npm workspaces and have webpack handle compiling of the main app and internal plugins.
- 43479e0: - Add settings link to the IDE Settings tab from the WordPress settings page.
- 1cfbdff: - **New Capability**: Introduced a new custom capability `manage_graphql_ide`. This capability allows administrators to control access to the WPGraphQL IDE functionalities. The capability has been assigned to the `administrator` role by default.

[View the full changelog](https://github.com/wp-graphql/wpgraphql-ide/blob/main/CHANGELOG.md)