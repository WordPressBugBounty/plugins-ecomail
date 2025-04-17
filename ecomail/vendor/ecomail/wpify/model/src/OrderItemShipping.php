<?php

declare (strict_types=1);
namespace EcomailDeps\Wpify\Model;

use EcomailDeps\Wpify\Model\Attributes\AccessorObject;
class OrderItemShipping extends OrderItem
{
    /**
     * Method ID.
     */
    #[AccessorObject]
    public string $method_id = '';
    /**
     * Instance ID.
     */
    #[AccessorObject]
    public string $instance_id = '';
    /**
     * Method title.
     */
    #[AccessorObject]
    public string $method_title = '';
}
