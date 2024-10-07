<?php

namespace EcomailDeps\Wpify\Model;

use EcomailDeps\Wpify\Model\Abstracts\AbstractTermModel;
use EcomailDeps\Wpify\Model\Interfaces\PostModelInterface;
use EcomailDeps\Wpify\Model\Relations\TermPostsRelation;
/**
 * Class Category
 * @package Wpify\Model
 *
 * @method CategoryRepository model_repository()
 */
class Category extends AbstractTermModel
{
    /** @var PostModelInterface */
    public $posts;
    protected function posts_relation() : TermPostsRelation
    {
        return new TermPostsRelation($this, 'posts', $this->model_repository()->get_post_repository());
    }
}
