<?php

namespace Ecomail;

/**
 * Class Admin
 *
 * @package WpifyWoo
 * @property Plugin $plugin
 */
class Admin {

	public function __construct() {
		$this->setup();
	}
	public function setup() {
		add_filter( 'plugin_action_links_ecomail/ecomail.php', array( $this, 'add_action_links' ) );
	}

	public function add_action_links( $links ) {
		$before = array(
			'settings' => sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php?page=ecomail' ), __( 'Settings', 'ecomail' ) ),
		);
		return array_merge( $before, $links );
	}
}
