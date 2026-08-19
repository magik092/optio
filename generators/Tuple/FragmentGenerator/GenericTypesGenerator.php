<?php

declare(strict_types=1);

namespace Optio\Generators\Tuple\FragmentGenerator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Optio\Generators\Tuple\FragmentGenerator;

class GenericTypesGenerator extends FragmentGenerator
{
    public function append(PhpNamespace $namespace, ClassType $class, int $tupleSize, int $maxTupleSize): void
    {
        $types = $this->types($tupleSize);

        foreach ($types as $type) {
            $class->addComment(sprintf('@template %s', $type));
        }
    }
}
