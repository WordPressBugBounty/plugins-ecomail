<?php

namespace Ecomail\Repositories;

use Ecomail\Settings;

class SettingsRepository {
	private $options = array();

	/**
	 * @param  string  $key
	 * @param  null  $default
	 *
	 * @return string|array
	 */
	public function get_option( $key = '', $default = null ) {
		if ( ! $this->options ) {
			$this->get_options();
		}

		if ( isset( $this->options[ $key ] ) ) {
			$value = $this->options[ $key ];
		} else {
			$value = $default ?: false;
		}

		return apply_filters( 'ecomail_option_value', $value, $key, $this->options );
	}

	/**
	 * Get all options
	 * @return array|mixed
	 */
	public function get_options() {
		if ( ! $this->options ) {
			$this->options = get_option( Settings::KEY, array() );
		}

		return $this->options;
	}

	public function get_settings_url() {
		return admin_url( 'admin.php?page=ecomail' );
	}
}
