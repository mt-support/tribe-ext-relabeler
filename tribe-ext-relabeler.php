<?php
/**
 * Plugin Name:         The Events Calendar Extension: Relabeler
 * Description:         Adds option to WP Admin > Events > Display for altering labels. For example, you can change the word "Events" to a different word such as "Gigs".
 * Plugin URI:          https://theeventscalendar.com/extensions/change-labels-events-venues-organizers/
 * GitHub Plugin URI:   https://github.com/mt-support/tribe-ext-relabeler
 * Version:             1.1.0
 * Extension Class:     Tribe\Extensions\Relabeler\Main
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

// Do not load unless Tribe Common is fully loaded.
if ( ! class_exists( 'Tribe__Extension' ) ) {
	return;
}

/**
 * Extension main class, class begins loading on init() function.
 */
class Main extends Tribe__Extension {

	/**
	 * Caches labels that are retrieved from the database.
	 *
	 * @var array {
	 *      @type $option_name string Full text for the altered label
	 * }
	 */
	protected $label_cache = [];

	/**
	 * Setup the Extension's properties.
	 *
	 * This always executes even if the required plugins are not present.
	 */
	public function construct() {
		$this->add_required_plugin( 'Tribe__Events__Main' );
	}

	/**
	 * Get this plugin's options prefix.
	 *
	 * Settings_Helper will append a trailing underscore before each option.
	 *
	 * @see \Tribe\Extensions\Example\Settings::set_options_prefix()
	 *
	 * @return string
	 */
	private function get_options_prefix() {
		return (string) str_replace( '-', '_', 'tribe-ext-relabeler' );
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Settings
	 */
	private function get_settings() {
		if ( empty( $this->settings ) ) {
			$this->settings = new Settings( $this->get_options_prefix() );
		}

		return $this->settings;
	}

	/**
	 * Extension initialization and hooks.
	 */
	public function init() {

		load_plugin_textdomain( 'tribe-ext-relabeler', false, basename( dirname( __FILE__ ) ) . '/languages/' );

		if ( ! $this->php_version_check() ) {
			return;
		}

		$this->class_loader();

		$this->get_settings();

		// Events.
		add_filter( 'tribe_event_label_singular', [ $this, 'get_event_single' ] );
		add_filter( 'tribe_event_label_singular_lowercase', [ $this, 'get_event_single_lowercase' ] );
		add_filter( 'tribe_event_label_plural', [ $this, 'get_event_plural' ] );
		add_filter( 'tribe_event_label_plural_lowercase', [ $this, 'get_event_plural_lowercase' ] );

		// Venues.
		add_filter( 'tribe_venue_label_singular', [ $this, 'get_venue_single' ] );
		add_filter( 'tribe_venue_label_singular_lowercase', [ $this, 'get_venue_single_lowercase' ] );
		add_filter( 'tribe_venue_label_plural', [ $this, 'get_venue_plural' ] );
		add_filter( 'tribe_venue_label_plural_lowercase', [ $this, 'get_venue_plural_lowercase' ] );

		// Organizers.
		add_filter( 'tribe_organizer_label_singular', [ $this, 'get_organizer_single' ] );
		add_filter( 'tribe_organizer_label_singular_lowercase', [ $this, 'get_organizer_single_lowercase' ] );
		add_filter( 'tribe_organizer_label_plural', [ $this, 'get_organizer_plural' ] );
		add_filter( 'tribe_organizer_label_plural_lowercase', [ $this, 'get_organizer_plural_lowercase' ] );
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
				$message .= sprintf( __( '%s requires PHP version %s or newer to work. Please contact your website host and inquire about updating PHP.', 'tribe-ext-relabeler' ), $this->get_name(), $php_required_version );
				$message .= sprintf( ' <a href="%1$s">%1$s</a>', 'https://wordpress.org/about/requirements/' );
				$message .= '</p>';

				tribe_notice( 'tribe-ext-relabeler-php-version', $message, [ 'type' => 'error' ] );
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
				__NAMESPACE__ . '\\',
				__DIR__ . DIRECTORY_SEPARATOR . 'src'
			);
		}

		$this->class_loader->register_autoloader();

		return $this->class_loader;
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

		$key = $this->get_options_prefix() . "_" . $key;

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
