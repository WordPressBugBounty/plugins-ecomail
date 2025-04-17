<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */
declare (strict_types=1);
namespace EcomailDeps\Wpify\Model\Attributes;

use Attribute;
use EcomailDeps\Wpify\Model\Exceptions\RepositoryNotFoundException;
use EcomailDeps\Wpify\Model\Interfaces\ModelInterface;
use EcomailDeps\Wpify\Model\Interfaces\SourceAttributeInterface;
/**
 * Get a list of models by ids.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class IdsRelation implements SourceAttributeInterface
{
    /**
     * Constructor for the IdsRelation attribute.
     *
     * @param string $source_key The aliased property.
     * @param class-string $target_entity The target entity.
     */
    public function __construct(public string $source_key, public string $target_entity, public array $args = array())
    {
    }
    /**
     * Get the aliased property.
     *
     * @param ModelInterface $model
     * @param string $key
     *
     * @return mixed
     * @throws RepositoryNotFoundException
     */
    public function get(ModelInterface $model, string $key): array
    {
        $manager = $model->manager();
        $repository = $manager->get_model_repository($this->target_entity);
        return $repository->find_by_ids($model->{$this->source_key}, $this->args);
    }
}
