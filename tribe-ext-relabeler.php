<?php
/**
 * Plugin Name:         The Events Calendar Extension: Relabeler
 * Description:         Adds option to WP Admin > Events > Display for altering labels. For example, you can change the word "Events" to a different word such as "Gigs".
 * Plugin URI:          https://theeventscalendar.com/extensions/change-labels-events-venues-organizers/
 * GitHub Plugin URI:   https://github.com/mt-support/tribe-ext-relabeler
 * Version:             1.0.2
 * Extension Class:     Tribe__Extension__Relabeler
 * Author:              Modern Tribe, Inc.
 * Author URI:          http://m.tri.be/1971
 * License:             GPLv3 or later
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:         tribe-ext-relabeler
 */

namespace Tribe\Extensions\Relabeler;

use Tribe__Autoloader;
use Tribe__Extension;

/**
 * Define Constants
 */

if ( ! defined( __NAMESPACE__ . '\NS' ) ) {
	define( __NAMESPACE__ . '\NS', __NAMESPACE__ . '\\' );
}

if ( ! defined( \Tribe\Extensions\Relabeler\NS . 'PLUGIN_TEXT_DOMAIN' ) ) {
	// `Tribe\Extensions\Example\PLUGIN_TEXT_DOMAIN` is defined
	define( NS . 'PLUGIN_TEXT_DOMAIN', 'tribe-ext-relabeler' );
}

// Do not load unless Tribe Common is fully loaded.
if ( ! class_exists( 'Tribe__Extension' ) ) {
	return;
}

/**
 * Extension main class, class begins loading on init() function.
 */
class Tribe__Extension__Relabeler extends Tribe__Extension {

	/**
	 * Caches labels that are retrieved from the database.
	 *
	 * @var array {
	 *      @type $option_name string Full text for the altered label
	 * }
	 */
	protected $label_cache = array();

	/**
	 * Setup the Extension's properties.
	 *
	 * This always executes even if the required plugins are not present.
	 */
	public function construct() {
		$this->add_required_plugin( 'Tribe__Events__Main' );
		$this->set_url( 'https://theeventscalendar.com/extensions/extensions/change-labels-events-venues-organizers/' );
		$this->set_version( '1.0.1' );
	}

	/**
	 * Extension initialization and hooks.
	 */
	public function init() {

		load_plugin_textdomain( 'PLUGIN_TEXT_DOMAIN', false, basename( dirname( __FILE__ ) ) . '/languages/' );

		if ( ! $this->php_version_check() ) {
			return;
		}

		$this->class_loader();

		// Settings area.
		if ( ! class_exists( 'Tribe__Extension__Settings_Helper' ) ) {
			require_once dirname( __FILE__ ) . '/src/Tribe/Settings_Helper.php';
		}
		add_action( 'admin_init', array( $this, 'add_settings' ) );

		// Events.
		add_filter( 'tribe_event_label_singular', array( $this, 'get_event_single' ) );
		add_filter( 'tribe_event_label_singular_lowercase', array( $this, 'get_event_single_lowercase' ) );
		add_filter( 'tribe_event_label_plural', array( $this, 'get_event_plural' ) );
		add_filter( 'tribe_event_label_plural_lowercase', array( $this, 'get_event_plural_lowercase' ) );

		// Venues.
		add_filter( 'tribe_venue_label_singular', array( $this, 'get_venue_single' ) );
		add_filter( 'tribe_venue_label_singular_lowercase', array( $this, 'get_venue_single_lowercase' ) );
		add_filter( 'tribe_venue_label_plural', array( $this, 'get_venue_plural' ) );
		add_filter( 'tribe_venue_label_plural_lowercase', array( $this, 'get_venue_plural_lowercase' ) );

		// Organizers.
		add_filter( 'tribe_organizer_label_singular', array( $this, 'get_organizer_single' ) );
		add_filter( 'tribe_organizer_label_singular_lowercase', array( $this, 'get_organizer_single_lowercase' ) );
		add_filter( 'tribe_organizer_label_plural', array( $this, 'get_organizer_plural' ) );
		add_filter( 'tribe_organizer_label_plural_lowercase', array( $this, 'get_organizer_plural_lowercase' ) );
	}

	/**
	 * Check if we have a sufficient version of PHP. Admin notice if we don't and user should see it.
	 *
	 * @link https://theeventscalendar.com/knowledgebase/php-version-requirement-changes/ All extensions require PHP 5.6+.
	 *
	 * @return bool
	 */
	private function php_version_check() {
		$php_required_version = '5.6';

		if ( version_compare( PHP_VERSION, $php_required_version, '<' ) ) {
			if (
				is_admin()
				&& current_user_can( 'activate_plugins' )
			) {
				$message = '<p>';
				$message .= sprintf( __( '%s requires PHP version %s or newer to work. Please contact your website host and inquire about updating PHP.', PLUGIN_TEXT_DOMAIN ), $this->get_name(), $php_required_version );
				$message .= sprintf( ' <a href="%1$s">%1$s</a>', 'https://wordpress.org/about/requirements/' );
				$message .= '</p>';

				tribe_notice( PLUGIN_TEXT_DOMAIN . '-php-version', $message, [ 'type' => 'error' ] );
			}

			return false;
		}

		return true;
	}

	/**
	 * Use Tribe Autoloader for all class files within this namespace in the 'src' directory.
	 *
	 * @return Tribe__Autoloader
	 */
	public function class_loader() {
		if ( empty( $this->class_loader ) ) {
			$this->class_loader = new Tribe__Autoloader;
			$this->class_loader->set_dir_separator( '\\' );
			$this->class_loader->register_prefix(
				\Tribe\Extensions\Relabeler\NS,
				__DIR__ . DIRECTORY_SEPARATOR . 'src'
			);
		}

		$this->class_loader->register_autoloader();

		return $this->class_loader;
	}

	/**
	 * Get an HTML link to the General settings tab
	 *
	 * @return string HTML link element to the general settings tab
	 */
	protected function general_settings_tab_link() {
		$url = Tribe__Settings::instance()->get_url( array( 'tab' => 'general' ) );

		return sprintf(
			'<a href="%2$s">%1$s</a>',
			esc_html__( 'General', 'tribe-extension' ),
			esc_url( $url )
		);
	}

	/**
	 * Gets the label from the database and caches it
	 *
	 * @param $key     string Option key for the label.
	 * @param $default string Value to return if none set.
	 *
	 * @return string|null
	 */
	public function get_label( $key, $default = null ) {
		if ( ! isset( $this->label_cache[ $key ] ) ) {
			$this->label_cache[ $key ] = tribe_get_option( $key, $default );
		}

		return $this->label_cache[ $key ];
	}

	/**
	 * Gets the label
	 *
	 * @param $label string
	 *
	 * @return string
	 */
	public function get_event_single( $label ) {
		return $this->get_label( 'label_event_single', $label );
	}

	/**
	 * Gets the label
	 *
	 * @param $label string
	 *
	 * @return string
	 */
	public function get_event_single_lowercase( $label ) {
		return $this->get_label( 'label_event_single_lowercase', $label );
	}

	/**
	 * Gets the label
	 *
	 * @param $label string
	 *
	 * @return string
	 */
	public function get_event_plural( $label ) {
		return $this->get_label( 'label_event_plural', $label );
	}

	/**
	 * Gets the label
	 *
	 * @param $label string
	 *
	 * @return string
	 */
	public function get_event_plural_lowercase( $label ) {
		return $this->get_label( 'label_event_plural_lowercase', $label );
	}

	/**
	 * Gets the label
	 *
	 * @param $label string
	 *
	 * @return string
	 */
	public function get_venue_single( $label ) {
		return $this->get_label( 'label_venue_single', $label );
	}

	/**
	 * Gets the label
	 *
	 * @param $label string
	 *
	 * @return string
	 */
	public function get_venue_single_lowercase( $label ) {
		return $this->get_label( 'label_venue_single_lowercase', $label );
	}

	/**
	 * Gets the label
	 *
	 * @param $label string
	 *
	 * @return string
	 */
	public function get_venue_plural( $label ) {
		return $this->get_label( 'label_venue_plural', $label );
	}

	/**
	 * Gets the label
	 *
	 * @param $label string
	 *
	 * @return string
	 */
	public function get_venue_plural_lowercase( $label ) {
		return $this->get_label( 'label_venue_plural_lowercase', $label );
	}

	/**
	 * Gets the label
	 *
	 * @param $label string
	 *
	 * @return string
	 */
	public function get_organizer_single( $label ) {
		return $this->get_label( 'label_organizer_single', $label );
	}

	/**
	 * Gets the label
	 *
	 * @param $label string
	 *
	 * @return string
	 */
	public function get_organizer_single_lowercase( $label ) {
		return $this->get_label( 'label_organizer_single_lowercase', $label );
	}

	/**
	 * Gets the label
	 *
	 * @param $label string
	 *
	 * @return string
	 */
	public function get_organizer_plural( $label ) {
		return $this->get_label( 'label_organizer_plural', $label );
	}

	/**
	 * Gets the label
	 *
	 * @param $label string
	 *
	 * @return string
	 */
	public function get_organizer_plural_lowercase( $label ) {
		return $this->get_label( 'label_organizer_plural_lowercase',  $label );
	}
}
