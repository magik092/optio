<?php

declare(strict_types=1);

namespace Optio\Generators\Tuple\FragmentGenerator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Optio\Generators\Tuple\FragmentGenerator;

class ArityMethodGenerator extends FragmentGenerator
{
    public function append(PhpNamespace $namespace, ClassType $class, int $tupleSize, int $maxTupleSize): void
    {
        $arity = $class->addMethod('arity');
        $arity->setReturnType('int');
        $arity->setBody('return ?;', [$tupleSize]);
    }
}
