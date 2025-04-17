<?php

declare (strict_types=1);
namespace EcomailDeps\Wpify\Model;

use EcomailDeps\Wpify\Model\Attributes\TermPostsRelation;
class ProductCat extends Term
{
    /**
     * Products assigned to this tag.
     *
     * @var Post[]
     */
    #[TermPostsRelation(Product::class)]
    public array $products = array();
}
