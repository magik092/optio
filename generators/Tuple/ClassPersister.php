<?php

declare(strict_types=1);

namespace Optio\Generators\Tuple;

interface ClassPersister
{
    public function save(string $directory, string $className, string $content): void;

    public function moveClass(string $fromDir, string $toDir, string $className): void;

    public function ensureDirectoryExists(string $directory): void;
}
