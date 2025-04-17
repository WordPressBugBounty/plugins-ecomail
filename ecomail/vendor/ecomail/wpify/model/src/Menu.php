<?php

declare (strict_types=1);
namespace EcomailDeps\Wpify\Model;

use EcomailDeps\Wpify\Model\Attributes\AliasOf;
use EcomailDeps\Wpify\Model\Attributes\MenuItemsRelation;
class Menu extends Term
{
    /**
     * Menu items.
     *
     * @var MenuItem[] $children
     */
    #[MenuItemsRelation(MenuItem::class)]
    public array $children = array();
    /**
     * @deprecated Use $children instead.
     *
     * @var MenuItem[] $items
     */
    #[AliasOf('children')]
    public array $items = array();
    /**
     * Converts the model to an array.
     *
     * @param array $props
     * @param array $recursive
     *
     * @return array
     */
    public function to_array(array $props = array(), array $recursive = array()): array
    {
        return parent::to_array($props, array_merge(array('children'), $recursive));
    }
}
