<?php

declare (strict_types=1);
namespace EcomailDeps\DI;

use EcomailDeps\Psr\Container\ContainerExceptionInterface;
/**
 * Exception for the Container.
 */
class DependencyException extends \Exception implements ContainerExceptionInterface
{
}
