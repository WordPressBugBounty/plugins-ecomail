<?php

declare (strict_types=1);
namespace EcomailDeps\PHPStan\PhpDocParser\Ast\ConstExpr;

use EcomailDeps\PHPStan\PhpDocParser\Ast\NodeAttributes;
class ConstExprArrayNode implements ConstExprNode
{
    use NodeAttributes;
    /** @var ConstExprArrayItemNode[] */
    public $items;
    /**
     * @param ConstExprArrayItemNode[] $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }
    public function __toString() : string
    {
        return '[' . \implode(', ', $this->items) . ']';
    }
}
