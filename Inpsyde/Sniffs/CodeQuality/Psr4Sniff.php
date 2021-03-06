<?php

/*
 * This file is part of the php-coding-standards package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Inpsyde\Sniffs\CodeQuality;

use Inpsyde\PhpcsHelpers;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class Psr4Sniff implements Sniff
{
    /**
     * @var array
     */
    public $psr4;

    /**
     * @var array
     */
    public $exclude = [];

    /**
     * @return int[]
     */
    public function register()
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT];
    }

    /**
     * @param File $file
     * @param int $position
     * @return void
     */
    public function process(File $file, $position)
    {
        $className = $file->getDeclarationName($position);
        $code = $file->getTokens()[$position]['code'];
        $entityType = 'class';
        if ($code !== T_CLASS) {
            $entityType = $code === T_TRAIT ? 'trait' : 'interface';
        }

        if (!$this->psr4 || !is_array($this->psr4)) {
            $this->checkFilenameOnly($file, $position, $className, $entityType);

            return;
        }

        $this->exclude = is_array($this->exclude) ? $this->normalizeExcluded($this->exclude) : [];
        $this->checkPsr4($file, $position, $className, $entityType);
    }

    private function checkFilenameOnly(
        File $file,
        int $position,
        string $className,
        string $entityType
    ) {

        if (basename($file->getFilename()) === "{$className}.php") {
            return;
        }

        $file->addError(
            sprintf(
                "File containing %s '%s' is named '%s' instead of '%s'.",
                $entityType,
                $className,
                $file->getFilename(),
                "{$className}.php"
            ),
            $position,
            'WrongFilename'
        );
    }

    /**
     * phpcs:disable Generic.Metrics.NestingLevel
     */
    private function checkPsr4(File $file, int $position, string $className, string $entityType)
    {
        // phpcs:enable Generic.Metrics.NestingLevel

        list(, $namespace) = PhpcsHelpers::findNamespace($file, $position);
        $namespace = is_string($namespace) ? "{$namespace}\\" : '';
        $namespace = rtrim($namespace, '\\');

        $fullyQualifiedName = $namespace . "\\{$className}";

        foreach ($this->exclude as $excluded) {
            if (strpos($fullyQualifiedName, $excluded) === 0) {
                return;
            }
        }

        $filePath = str_replace('\\', '/', $file->getFilename());

        foreach ($this->psr4 as $baseNamespace => $foldersStr) {
            $baseNamespace = trim($baseNamespace, '\\');
            if (strpos($namespace, $baseNamespace) !== 0) {
                continue;
            }

            $folders = explode('|', $foldersStr);

            foreach ($folders as $folder) {
                $folder = trim(str_replace('\\', '/', $folder), './');
                $folderSplit = explode("/{$folder}/", $filePath);
                if (count($folderSplit) < 2) {
                    continue;
                }

                $relativePath = array_pop($folderSplit);

                if (basename($relativePath) !== "{$className}.php") {
                    continue;
                }

                $relativeNamespace = str_replace('/', '\\', dirname($relativePath));
                $expectedNamespace = $relativeNamespace === '.'
                    ? $baseNamespace
                    : "{$baseNamespace}\\{$relativeNamespace}";

                if ("{$expectedNamespace}\\{$className}" === "{$namespace}\\{$className}") {
                    return;
                }
            }
        }

        $file->addError(
            sprintf(
                "%s '%s', located at '%s', is not compliant with PSR-4 configuration.",
                ucfirst($entityType),
                $fullyQualifiedName,
                $filePath
            ),
            $position,
            'InvalidPSR4'
        );
    }

    private function normalizeExcluded(array $excluded): array
    {
        return array_map(
            function (string $className): string {
                return ltrim($className, '\\');
            },
            $excluded
        );
    }
}
