<?php

declare (strict_types=1);
namespace EcomailDeps\PHPStan\PhpDocParser\Ast\Type;

use EcomailDeps\PHPStan\PhpDocParser\Ast\NodeAttributes;
class ThisTypeNode implements TypeNode
{
    use NodeAttributes;
    public function __toString() : string
    {
        return '$this';
    }
}
