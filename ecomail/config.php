<?php

use EcomailDeps\DI\Definition\Helper\CreateDefinitionHelper;
use EcomailDeps\Wpify\CustomFields\CustomFields;
use EcomailDeps\Wpify\Model\Manager;
use EcomailDeps\Wpify\PluginUtils\PluginUtils;

return array(
	CustomFields::class => ( new CreateDefinitionHelper() )
		->constructor( plugins_url( 'deps/wpify/custom-fields', __FILE__ ) ),
	PluginUtils::class  => ( new CreateDefinitionHelper() )
		->constructor( __DIR__ . '/ecomail.php' ),
	Manager::class      => ( new CreateDefinitionHelper() )
		->constructor( array() ),
);
