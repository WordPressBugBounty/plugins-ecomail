<?php

declare (strict_types=1);
namespace EcomailDeps\PHPStan\PhpDocParser\Ast\PhpDoc;

use EcomailDeps\PHPStan\PhpDocParser\Ast\NodeAttributes;
use EcomailDeps\PHPStan\PhpDocParser\Ast\Type\TypeNode;
class ThrowsTagValueNode implements PhpDocTagValueNode
{
    use NodeAttributes;
    /** @var TypeNode */
    public $type;
    /** @var string (may be empty) */
    public $description;
    public function __construct(TypeNode $type, string $description)
    {
        $this->type = $type;
        $this->description = $description;
    }
    public function __toString() : string
    {
        return \trim("{$this->type} {$this->description}");
    }
}
