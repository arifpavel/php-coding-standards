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
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class HookClosureReturnSniff implements Sniff
{
    /**
     * @return int[]
     */
    public function register()
    {
        return [T_CLOSURE];
    }

    /**
     * @param File $file
     * @param int $position
     * @return void
     */
    public function process(File $file, $position)
    {
        if (!PhpcsHelpers::isHookClosure($file, $position)) {
            return;
        }

        list($functionStart, $functionEnd) = PhpcsHelpers::functionBoundaries($file, $position);
        if ($functionStart < 0 || $functionEnd <= 0) {
            return;
        }

        list(
            $nonVoidReturnCount,
            $voidReturnCount,
            $nullReturnsCount
        ) = PhpcsHelpers::countReturns($file, $position);

        // Allow a filter to return null on purpose
        $nonVoidReturnCount += $nullReturnsCount;

        $isFilterClosure = PhpcsHelpers::isHookClosure($file, $position, true, false);

        if ($isFilterClosure && (!$nonVoidReturnCount || $voidReturnCount)) {
            $file->addError(
                'No (or void) return from filter closure.',
                $position,
                'NoReturnFromFilter'
            );
        }

        if (!$isFilterClosure && $nonVoidReturnCount) {
            $file->addError('Return value from action closure.', $position, 'ReturnFromAction');
        }
    }
}
