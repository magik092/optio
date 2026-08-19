<?php

declare(strict_types=1);

namespace Optio\Generators\Tuple;

class FilePutContentsClassPersister implements ClassPersister
{
    public function __construct(private string $sourcePath)
    {
    }

    public function save(string $directory, string $className, string $content): void
    {
        $filePath = $this->sourcePath.$directory.'/'.$className.'.php';

        if (false === file_put_contents($filePath, $content)) {
            throw new \RuntimeException(sprintf('Failed to write file: %s', $filePath));
        }
    }

    public function moveClass(string $fromDir, string $toDir, string $className): void
    {
        $fromPath = sprintf('%s%s/%s.php', $this->sourcePath, $fromDir, $className);
        $toPath = sprintf('%s%s/%s.php', $this->sourcePath, $toDir, $className);

        if (false === copy($fromPath, $toPath)) {
            throw new \RuntimeException(sprintf('Failed to copy file from %s to %s', $fromPath, $toPath));
        }
    }

    public function ensureDirectoryExists(string $directory): void
    {
        $path = $this->sourcePath.$directory;

        if (!is_dir($path) && !mkdir($path, 0o755, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Failed to create directory: %s', $path));
        }
    }
}
