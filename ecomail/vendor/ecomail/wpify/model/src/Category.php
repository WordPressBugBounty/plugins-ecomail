<?php

declare (strict_types=1);
namespace EcomailDeps\Wpify\Model;

use EcomailDeps\Wpify\Model\Attributes\TermPostsRelation;
class Category extends Term
{
    /**
     * Posts assigned to this category.
     *
     * @var Post[]
     */
    #[TermPostsRelation(Post::class)]
    public array $posts = array();
}
