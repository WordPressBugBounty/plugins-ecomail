<?php

namespace EcomailDeps\Wpify\Model\Relations;

use EcomailDeps\Wpify\Model\Interfaces\ModelInterface;
use EcomailDeps\Wpify\Model\Interfaces\PostModelInterface;
use EcomailDeps\Wpify\Model\Interfaces\PostRepositoryInterface;
use EcomailDeps\Wpify\Model\Interfaces\RelationInterface;
use EcomailDeps\Wpify\Model\Interfaces\RepositoryInterface;
use EcomailDeps\Wpify\Model\Order;
use EcomailDeps\Wpify\Model\OrderItemRepository;
class OrderItemsRelation implements RelationInterface
{
    /** @var Order */
    private $model;
    /** @var OrderItemRepository */
    private $repository;
    private $type;
    /**
     * TermRelation constructor.
     *
     * @param PostModelInterface      $model
     * @param PostRepositoryInterface $repository
     */
    public function __construct(ModelInterface $model, RepositoryInterface $repository, string $type = 'line_item')
    {
        $this->model = $model;
        $this->repository = $repository;
        $this->type = $type;
    }
    public function fetch()
    {
        $items = [];
        foreach ($this->model->source_object()->get_items($this->type) as $item) {
            $items[] = $this->repository->get($item);
        }
        return $items;
    }
    public function assign()
    {
    }
}
