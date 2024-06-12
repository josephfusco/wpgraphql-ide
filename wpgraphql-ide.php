<?php
/**
 * Plugin Name:       WPGraphQL IDE
 * Description:       A next-gen query editor for WPGraphQL.
 * Author:            WPGraphQL, Joseph Fusco
 * Author URI:        https://github.com/josephfusco
 * GitHub Plugin URI: https://github.com/wp-graphql/wpgraphql-ide
 * License:           GPLv3 or later
 * Text Domain:       wpgraphql-ide
 * Version:           2.1.2
 * Requires PHP:      7.4
 * Tested up to:      6.5
 *
 * @package WPGraphQLIDE
 */

namespace WPGraphQLIDE;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPGRAPHQL_IDE_VERSION', '2.0.0' );
define( 'WPGRAPHQL_IDE_ROOT_ELEMENT_ID', 'wpgraphql-ide-root' );
define( 'WPGRAPHQL_IDE_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPGRAPHQL_IDE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Register core IDE plugins.
 */
require_once WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'plugins/query-composer-panel/query-composer-panel.php';
require_once WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'plugins/help-panel/help-panel.php';

/**
 * Retrieves the custom capabilities and their associated roles for the plugin.
 *
 * @return array<string,mixed> The array of custom capabilities and roles.
 */
function get_custom_capabilities() {
	return [
		'manage_graphql_ide' => [ 'administrator' ],
	];
}

/**
 * Generate a hash for the capabilities array.
 *
 * @param array<string,mixed> $capabilities Array of capabilities and roles.
 * @return string MD5 hash of the capabilities array.
 */
function generate_capabilities_hash( $capabilities ) {
	return md5( wp_json_encode( $capabilities ) );
}

/**
 * Check if the capabilities hash has changed.
 *
 * @param string $current_hash Current hash of the capabilities array.
 * @return bool True if the hash has changed, false otherwise.
 */
function has_capabilities_hash_changed( $current_hash ) {
	$stored_hash = get_option( 'wpgraphql_ide_capabilities' );
	return $current_hash !== $stored_hash;
}

/**
 * Update the capabilities for the specified roles.
 *
 * @param array<string,mixed> $capabilities Array of capabilities and roles.
 */
function update_roles_capabilities( $capabilities ) {
	foreach ( $capabilities as $capability => $roles ) {
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );

			if ( $role && ! $role->has_cap( $capability ) ) {
				$role->add_cap( $capability );
			}
		}
	}
}

/**
 * Save the new capabilities hash in the options table.
 *
 * @param string $current_hash Current hash of the capabilities array.
 */
function save_capabilities_hash( $current_hash ) {
	update_option( 'wpgraphql_ide_capabilities', $current_hash );
}

/**
 * Adds custom capabilities to specified roles.
 */
function add_custom_capabilities() {
	$capabilities = get_custom_capabilities();
	$current_hash = generate_capabilities_hash( $capabilities );

	if ( ! has_capabilities_hash_changed( $current_hash ) ) {
		return;
	}

	update_roles_capabilities( $capabilities );
	save_capabilities_hash( $current_hash );
}
add_action( 'init', __NAMESPACE__ . '\\add_custom_capabilities' );

/**
 * Checks if the current user has the capability required to load scripts and styles for the GraphQL IDE.
 *
 * @return bool Whether the user has the required capability.
 */
function user_has_graphql_ide_capability(): bool {
	$capability_required = apply_filters( 'wpgraphqlide_capability_required', 'manage_graphql_ide' );

	return current_user_can( $capability_required );
}

/**
 * Determines if the current admin page is a dedicated WPGraphQL IDE page.
 *
 * @return bool True if the current page is a dedicated WPGraphQL IDE page, false otherwise.
 */
function current_screen_is_dedicated_ide_page(): bool {
	return is_ide_page() || is_legacy_ide_page();
}

/**
 * Checks if the current admin page is the new WPGraphQL IDE page.
 *
 * @return bool True if the current page is the new WPGraphQL IDE page, false otherwise.
 */
function is_ide_page(): bool {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}

	$screen = get_current_screen();
	if ( ! $screen ) {
		return false;
	}

	return 'graphql_page_graphql-ide' === $screen->id;
}

/**
 * Checks if the current admin page is the legacy GraphiQL IDE page.
 *
 * @return bool True if the current page is the legacy GraphiQL IDE page, false otherwise.
 */
function is_legacy_ide_page(): bool {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}

	$screen = get_current_screen();
	if ( ! $screen ) {
		return false;
	}

	return 'toplevel_page_graphiql-ide' === $screen->id;
}

/**
 * Registers the plugin's custom menu item in the WordPress Admin Bar.
 *
 * @global WP_Admin_Bar $wp_admin_bar The WordPress Admin Bar instance.
 */
function register_wpadminbar_menus(): void {
	if ( ! user_has_graphql_ide_capability() ) {
		return;
	}

	global $wp_admin_bar;

	$app_context = get_app_context();

	// Retrieve the settings array
	$graphql_ide_settings = get_option( 'graphql_ide_settings', [] );

	// Get the specific link behavior value, default to 'drawer' if not set
	$link_behavior = isset( $graphql_ide_settings['graphql_ide_link_behavior'] ) ? $graphql_ide_settings['graphql_ide_link_behavior'] : 'drawer';

	if ( 'drawer' === $link_behavior && ! current_screen_is_dedicated_ide_page() ) {
		// Drawer Button
		$wp_admin_bar->add_node(
			[
				'id'    => 'wpgraphql-ide',
				'title' => '<div id="' . esc_attr( WPGRAPHQL_IDE_ROOT_ELEMENT_ID ) . '"><span class="ab-icon"></span>' . $app_context['drawerButtonLabel'] . '</div>',
				'href'  => '#',
			]
		);
	} elseif ( 'disabled' !== $link_behavior ) {
		// Link to the new dedicated IDE page.
		$wp_admin_bar->add_node(
			[
				'id'    => 'wpgraphql-ide',
				'title' => '<span class="ab-icon"></span>' . $app_context['drawerButtonLabel'],
				'href'  => admin_url( 'admin.php?page=graphql-ide' ),
			]
		);
	}
}
add_action( 'admin_bar_menu', __NAMESPACE__ . '\\register_wpadminbar_menus', 999 );

/**
 * Registers a submenu page for the dedicated GraphQL IDE.
 *
 * @see add_submenu_page() For more information on adding submenu pages.
 * @link https://developer.wordpress.org/reference/functions/add_submenu_page/
 */
function register_dedicated_ide_menu(): void {
	if ( ! user_has_graphql_ide_capability() ) {
		return;
	}

	// Remove the legacy submenu without affecting the ability to directly link to the legacy IDE (wp-admin/admin.php?page=graphiql-ide)
	$graphql_ide_settings = get_option( 'graphql_ide_settings', [] );
	$show_legacy_editor   = isset( $graphql_ide_settings['graphql_ide_show_legacy_editor'] ) ? $graphql_ide_settings['graphql_ide_show_legacy_editor'] : 'off';

	if ( 'off' === $show_legacy_editor ) {
		remove_submenu_page( 'graphiql-ide', 'graphiql-ide' );
	}

	add_submenu_page(
		'graphiql-ide',
		__( 'GraphQL IDE', 'wpgraphql-ide' ),
		__( 'GraphQL IDE', 'wpgraphql-ide' ),
		'manage_graphql_ide',
		'graphql-ide',
		__NAMESPACE__ . '\\render_dedicated_ide_page'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\\register_dedicated_ide_menu' );

/**
 * Renders the container for the dedicated IDE page for the React app to be mounted to.
 */
function render_dedicated_ide_page(): void {
	echo '<div id="' . esc_attr( WPGRAPHQL_IDE_ROOT_ELEMENT_ID ) . '"></div>';
}

/**
 * Enqueues custom CSS to set the "GraphQL IDE" menu item icon in the WordPress Admin Bar.
 */
function enqueue_graphql_ide_menu_icon_css(): void {
	if ( ! user_has_graphql_ide_capability() ) {
		return;
	}

	$custom_css = '
        #wp-admin-bar-wpgraphql-ide .ab-icon::before,
        #wp-admin-bar-wpgraphql-ide .ab-icon::before {
            background-image: url("data:image/svg+xml;base64,' . base64_encode( graphql_logo_svg() ) . '");
            background-size: 100%;
            border-radius: 12px;
            box-sizing: border-box;
            content: "";
            display: inline-block;
            height: 24px;
            width: 24px;
        }
    ';

	wp_add_inline_style( 'admin-bar', $custom_css );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_graphql_ide_menu_icon_css' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_graphql_ide_menu_icon_css' );

/**
 * Enqueues the React application script and associated styles.
 */
function enqueue_react_app_with_styles(): void {
	if ( is_legacy_ide_page() ) {
		return;
	}

	if ( ! class_exists( '\WPGraphQL\Router' ) ) {
		return;
	}

	if ( ! user_has_graphql_ide_capability() ) {
		return;
	}

	// Don't enqueue new styles/scripts on the legacy IDE page
	if ( function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( 'toplevel_page_graphiql-ide' === $screen->id ) {
			return;
		}
	}

	$asset_file         = include WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'build/wpgraphql-ide.asset.php';
	$render_asset_file  = include WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'build/wpgraphql-ide-render.asset.php';
	$graphql_asset_file = include WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'build/graphql.asset.php';

	$app_context = get_app_context();

	wp_register_script(
		'graphql',
		plugins_url( 'build/graphql.js', __FILE__ ),
		$graphql_asset_file['dependencies'],
		$graphql_asset_file['version'],
		false
	);

	wp_enqueue_script(
		'wpgraphql-ide',
		plugins_url( 'build/wpgraphql-ide.js', __FILE__ ),
		array_merge( $asset_file['dependencies'], [ 'graphql' ] ),
		$asset_file['version'],
		false
	);

	$localized_data = [
		'nonce'               => wp_create_nonce( 'wp_rest' ),
		'graphqlEndpoint'     => trailingslashit( site_url() ) . 'index.php?' . \WPGraphQL\Router::$route,
		'rootElementId'       => WPGRAPHQL_IDE_ROOT_ELEMENT_ID,
		'context'             => $app_context,
		'isDedicatedIdePage'  => current_screen_is_dedicated_ide_page(),
		'dedicatedIdeBaseUrl' => get_dedicated_ide_base_url(),
	];

	wp_localize_script(
		'wpgraphql-ide',
		'WPGRAPHQL_IDE_DATA',
		$localized_data
	);

	// Extensions looking to extend GraphiQL can hook in here,
	// after the window object is established, but before the App renders
	do_action( 'wpgraphqlide_enqueue_script', $app_context );

	wp_enqueue_script(
		'wpgraphql-ide-render',
		plugins_url( 'build/wpgraphql-ide-render.js', __FILE__ ),
		array_merge( $asset_file['dependencies'], [ 'wpgraphql-ide', 'graphql' ] ),
		$render_asset_file['version'],
		false
	);

	wp_enqueue_style( 'wpgraphql-ide-app', plugins_url( 'build/wpgraphql-ide.css', __FILE__ ), [], $asset_file['version'] );
	wp_enqueue_style( 'wpgraphql-ide-render', plugins_url( 'build/wpgraphql-ide-render.css', __FILE__ ), [], $asset_file['version'] );

	// Avoid running custom styles through a build process for an improved developer experience.
	wp_enqueue_style( 'wpgraphql-ide', plugins_url( 'styles/wpgraphql-ide.css', __FILE__ ), [], $asset_file['version'] );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_react_app_with_styles' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_react_app_with_styles' );

/**
 * Retrieves the base URL for the dedicated WPGraphQL IDE page.
 *
 * @return string The URL for the dedicated IDE page within the WordPress admin.
 */
function get_dedicated_ide_base_url(): string {
	return menu_page_url( 'graphql-ide', false );
}

/**
 * Retrieves the specific header of this plugin.
 *
 * @param string $key The plugin data key.
 * @return string|null The version number of the plugin. Returns null if the version is not found.
 */
function get_plugin_header( string $key = '' ): ?string {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( empty( $key ) ) {
		return null;
	}

	$plugin_data = get_plugin_data( __FILE__ );

	return $plugin_data[ $key ] ?? null;
}

/**
 * Retrieves app context.
 *
 * @return array<string, mixed> The possibly filtered app context array.
 */
function get_app_context(): array {
	$current_user = wp_get_current_user();

	// Get the avatar URL for the current user. Returns an empty string if no user is logged in.
	$avatar_url = $current_user->exists() ? get_avatar_url( $current_user->ID ) : '';

	return apply_filters(
		'wpgraphqlide_context',
		[
			'pluginVersion'     => get_plugin_header( 'Version' ),
			'pluginName'        => get_plugin_header( 'Name' ),
			'externalFragments' => apply_filters( 'wpgraphqlide_external_fragments', [] ),
			'avatarUrl'         => $avatar_url,
			'drawerButtonLabel' => __( 'GraphQL IDE', 'wpgraphql-ide' ),
		]
	);
}

/**
 * Adds styles to hide generic admin notices on the GraphQL IDE page.
 *
 * @param array<int, mixed> $notices The array of notices to render.
 */
function graphql_admin_notices_render_notices( array $notices ) {
	echo '
    <style>
        body.graphql_page_graphql-ide #wpbody .wpgraphql-admin-notice {
            display: block;
            position: absolute;
            top: 0;
            right: 0;
            z-index: 1;
            min-width: 40%;
        }
        body.graphql_page_graphql-ide #wpbody .graphiql-container {
            padding-top: ' . count( $notices ) * 45 . 'px;
        }
        body.graphql_page_graphql-ide #wpgraphql-ide-root {
            height: calc(100vh - var(--wp-admin--admin-bar--height) - ' . count( $notices ) * 45 . 'px);
        }
    </style>
    ';
}
add_action( 'graphql_admin_notices_render_notices', __NAMESPACE__ . '\\graphql_admin_notices_render_notices', 10, 1 );

/**
 * Adds styles to apply top margin to notices added via register_graphql_admin_notice.
 *
 * @param string               $notice_slug The slug of the notice.
 * @param array<string, mixed> $notice The notice data.
 * @param bool                 $is_dismissable Whether the notice is dismissable.
 * @param int                  $count The count of notices.
 */
function graphql_admin_notices_render_notice( string $notice_slug, array $notice, bool $is_dismissable, int $count ) {
	echo '
    <style>
        body.graphql_page_graphql-ide #wpbody #wpgraphql-admin-notice-' . esc_attr( $notice_slug ) . ' {
            top: ' . esc_attr( ( $count * 45 ) . 'px' ) . ';
        }
    </style>
    ';
}
add_action( 'graphql_admin_notices_render_notice', __NAMESPACE__ . '\\graphql_admin_notices_render_notice', 10, 4 );

/**
 * Filters to allow GraphQL admin notices to be displayed on the dedicated IDE page.
 *
 * @param bool               $is_plugin_scoped_page True if the current page is within scope of the plugin's pages.
 * @param string             $current_page_id The ID of the current admin page.
 * @param array<int, string> $allowed_pages The list of allowed pages.
 * @return bool Whether the admin notice is allowed on the current page.
 */
function graphql_admin_notices_is_allowed_admin_page( bool $is_plugin_scoped_page, string $current_page_id, array $allowed_pages ): bool {
	// If the current page is the dedicated IDE page, we want to allow notices to be displayed.
	if ( 'graphql_page_graphql-ide' === $current_page_id ) {
		return true;
	}

	return $is_plugin_scoped_page;
}
add_filter( 'graphql_admin_notices_is_allowed_admin_page', __NAMESPACE__ . '\\graphql_admin_notices_is_allowed_admin_page', 10, 3 );

/**
 * Modifies the script tag for specific scripts to add the 'defer' attribute.
 *
 * @param string $tag The HTML <script> tag of the enqueued script.
 * @param string $handle The script's registered handle in WordPress.
 * @return string Modified script tag with 'defer' attribute included if handle matches; otherwise, unchanged.
 */
function add_defer_attribute_to_script( string $tag, string $handle ): string {
	if ( 'wpgraphql-ide' === $handle ) {
		return str_replace( ' src', ' defer="defer" src', $tag );
	}

	return $tag;
}
add_filter( 'script_loader_tag', __NAMESPACE__ . '\\add_defer_attribute_to_script', 10, 2 );

/**
 * Update the existing GraphiQL link field configuration to say "Legacy".
 *
 * @param array<string, mixed> $field_config The field configuration array.
 * @param string               $field_name The name of the field.
 * @param string               $section The section the field belongs to.
 * @return array<string, mixed> The modified field configuration array.
 */
function update_graphiql_link_field_config( array $field_config, string $field_name, string $section ): array {
	if ( 'show_graphiql_link_in_admin_bar' === $field_name && 'graphql_general_settings' === $section ) {
		$field_config['desc'] = sprintf(
			'%1$s<br><p class="description">%2$s</p>',
			__( 'Show the GraphiQL IDE link in the WordPress Admin Bar.', 'wpgraphql-ide' ),
			sprintf(
				/* translators: %s: Strong opening tag */
				__( '%1$sNote:%2$s This setting has been disabled by the new WPGraphQL IDE. Related settings are now available under the "IDE Settings" tab.', 'wpgraphql-ide' ),
				'<strong>',
				'</strong>'
			)
		);
		$field_config['disabled'] = true;
		$field_config['value']    = 'off';
	}
	return $field_config;
}
add_filter( 'graphql_setting_field_config', __NAMESPACE__ . '\\update_graphiql_link_field_config', 10, 3 );

/**
 * Ensure the `show_graphiql_link_in_admin_bar` setting is always unchecked.
 *
 * @param mixed                $value The value of the field.
 * @param mixed                $default_value The default value if there is no value set.
 * @param string               $option_name The name of the option.
 * @param array<string, mixed> $section_fields The setting values within the section.
 * @param string               $section_name The name of the section the setting belongs to.
 * @return mixed The modified value of the field.
 */
function ensure_graphiql_link_is_unchecked( $value, $default_value, $option_name, $section_fields, $section_name ) {
	if ( 'show_graphiql_link_in_admin_bar' === $option_name && 'graphql_general_settings' === $section_name ) {
		return 'off';
	}
	return $value;
}
add_filter( 'graphql_get_setting_section_field_value', __NAMESPACE__ . '\\ensure_graphiql_link_is_unchecked', 10, 5 );

/**
 * Registers custom GraphQL settings.
 */
function register_ide_settings(): void {
	// Add a tab section to the GraphQL admin settings page.
	register_graphql_settings_section(
		'graphql_ide_settings',
		[
			'title' => __( 'IDE Settings', 'wpgraphql-ide' ),
			'desc'  => __( 'Customize your WPGraphQL IDE experience sitewide. Individual users can override these settings in their user profile.', 'wpgraphql-ide' ),
		]
	);

	register_graphql_settings_field(
		'graphql_ide_settings',
		[
			'name'              => 'graphql_ide_link_behavior',
			'label'             => __( 'Admin Bar Link Behavior', 'wpgraphql-ide' ),
			'desc'              => __( 'How would you like to access the GraphQL IDE from the admin bar?', 'wpgraphql-ide' ),
			'type'              => 'radio',
			'options'           => [
				'drawer'         => __( 'Drawer (recommended) — open the IDE in a slide up drawer from any page', 'wpgraphql-ide' ),
				'dedicated_page' => sprintf(
					wp_kses_post(
						sprintf(
							/* translators: %s: URL to the GraphQL IDE page */
							__( 'Dedicated Page — direct link to <a href="%1$s">%1$s</a>', 'wpgraphql-ide' ),
							esc_url( admin_url( 'admin.php?page=graphql-ide' ) )
						)
					)
				),
				'disabled'       => __( 'Disabled — remove the IDE link from the admin bar', 'wpgraphql-ide' ),
			],
			'default'           => 'drawer',
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_custom_graphql_ide_link_behavior',
		]
	);

	register_graphql_settings_field(
		'graphql_ide_settings',
		[
			'name'  => 'graphql_ide_show_legacy_editor',
			'label' => __( 'Show Legacy Editor', 'wpgraphql-ide' ),
			'desc'  => __( 'Show the legacy editor', 'wpgraphql-ide' ),
			'type'  => 'checkbox',
		]
	);
}
add_action( 'graphql_register_settings', __NAMESPACE__ . '\\register_ide_settings' );

/**
 * Sanitize the input value for the custom GraphQL IDE link behavior setting.
 *
 * @param string $value The input value.
 * @return string The sanitized value.
 */
function sanitize_custom_graphql_ide_link_behavior( string $value ): string {
	$valid_values = [ 'drawer', 'dedicated_page', 'disabled' ];

	if ( in_array( $value, $valid_values, true ) ) {
		return $value;
	}

	return 'drawer';
}

/**
 * Adds a settings link to the plugin actions.
 *
 * @param array<int, string> $links The existing action links.
 * @return array<int, string> The modified action links.
 */
function add_settings_link( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=graphql-settings#graphql_ide_settings' ) ),
		esc_html__( 'Settings', 'wpgraphql-ide' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), __NAMESPACE__ . '\\add_settings_link' );

/**
 * Rename and reorder the submenu items under 'GraphQL'.
 */
function rename_reorder_submenu_items(): void {
	global $submenu;

	if ( isset( $submenu['graphiql-ide'] ) ) {
		$temp_submenu = $submenu['graphiql-ide'];
		foreach ( $temp_submenu as $key => $value ) {
			if ( 'GraphiQL IDE' === $value[0] ) {
				$temp_submenu[ $key ][0] = 'Legacy GraphQL IDE';
				$legacy_item             = $temp_submenu[ $key ];
				unset( $temp_submenu[ $key ] );
				$temp_submenu = array_values( $temp_submenu );
				array_splice( $temp_submenu, 1, 0, [ $legacy_item ] );
				break;
			}
		}
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu['graphiql-ide'] = $temp_submenu;
	}
}
add_action( 'admin_menu', __NAMESPACE__ . '\\rename_reorder_submenu_items', 999 );

/**
 * Generates the SVG logo for GraphQL.
 *
 * @return string The SVG logo markup.
 */
function graphql_logo_svg(): string {
	return <<<XML
        <svg width="160" height="160" viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="80" cy="80" r="64" fill="url(#paint0_radial_30_2860)"/>
            <g filter="url(#filter0_d_30_2860)">
            <path d="M81.5239 72.2556C84.2608 72.2556 86.4795 70.0369 86.4795 67.3C86.4795 64.5632 84.2608 62.3445 81.5239 62.3445C78.787 62.3445 76.5684 64.5632 76.5684 67.3C76.5684 70.0369 78.787 72.2556 81.5239 72.2556Z" fill="white"/>
            <path d="M118.588 90.4878C116.007 90.05 113.769 92.0116 113.736 94.5018C113.711 96.5294 112.592 98.4291 110.696 99.1476C107.17 100.49 103.825 97.9046 103.825 94.5555V67.5931C103.825 56.1994 95.3755 46.3915 84.0521 45.1403C71.8903 43.794 61.3928 52.3011 59.5262 63.6741C59.5262 63.6823 59.5179 63.6906 59.5096 63.6906C49.4457 65.8875 42 74.8365 42 85.4703V103.665C42 105.933 43.8377 107.77 46.1049 107.77H55.3718C57.6348 107.77 59.3527 105.92 59.3445 103.657C59.3321 100.213 62.8505 97.5742 66.4805 99.1518C68.2314 99.9157 69.2638 101.716 69.2556 103.624C69.2473 105.912 71.1015 107.766 73.3852 107.766H82.4952C84.7624 107.766 86.6 105.928 86.6 103.661V85.4951C86.6 84.8302 86.472 84.1612 86.1623 83.5748C85.3777 82.0757 83.8538 81.2291 82.2515 81.3159C82.0162 81.3283 81.7725 81.3365 81.5289 81.3365C73.7982 81.3365 67.4964 75.0471 67.4881 67.3164C67.4881 67.3123 67.4881 67.304 67.4881 67.2999L67.55 66.4657C68.058 59.5362 73.4678 53.8455 80.3973 53.3004C88.6483 52.6479 95.5737 59.181 95.5737 67.2958V94.3407C95.5737 100.663 100.666 106.779 106.926 107.638C114.954 108.741 121.863 102.575 121.999 94.7867C122.036 92.7137 120.641 90.8305 118.596 90.4837L118.588 90.4878ZM78.3367 89.7238V99.0981C78.3367 99.3252 78.1508 99.511 77.9237 99.511H77.1432C76.9697 99.511 76.8169 99.3995 76.7591 99.2343C74.9421 94.1053 70.0402 90.4258 64.3 90.4258C58.5598 90.4258 53.658 94.1095 51.8409 99.2343C51.7831 99.3995 51.6303 99.511 51.4527 99.511H50.6722C50.4451 99.511 50.2593 99.3252 50.2593 99.0981V85.4703C50.2593 79.4823 54.0048 74.3409 59.3279 72.3298C59.5592 72.2431 59.8111 72.3835 59.8689 72.623C61.9874 81.2333 69.1276 87.8985 77.9898 89.315C78.188 89.348 78.3367 89.5173 78.3367 89.7197V89.7238Z" fill="white"/>
            </g>
            <defs>
            <filter id="filter0_d_30_2860" x="34" y="37" width="96" height="78.7703" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
            <feFlood flood-opacity="0" result="BackgroundImageFix"/>
            <feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
            <feOffset/>
            <feGaussianBlur stdDeviation="4"/>
            <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0.141176 0 0 0 0 0.278431 0 0 0 0.1 0"/>
            <feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_30_2860"/>
            <feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_30_2860" result="shape"/>
            </filter>
            <radialGradient id="paint0_radial_30_2860" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(16 16) rotate(45) scale(181.019)">
            <stop stop-color="#0ECAD4"/>
            <stop offset="1" stop-color="#7A45E5"/>
            </radialGradient>
            </defs>
        </svg>
XML;
}
