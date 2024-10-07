<?php

declare (strict_types=1);
namespace EcomailDeps\PHPStan\PhpDocParser\Ast\ConstExpr;

use EcomailDeps\PHPStan\PhpDocParser\Ast\NodeAttributes;
class ConstExprNullNode implements ConstExprNode
{
    use NodeAttributes;
    public function __toString() : string
    {
        return 'null';
    }
}
