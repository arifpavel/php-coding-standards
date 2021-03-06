<?php

/*
 * This file is part of the php-coding-standards package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This file contains code from "phpcs-calisthenics-rules" repository
 * found at https://github.com/object-calisthenics
 * Copyright (c) 2014 Doctrine Project
 * released under MIT license.
 */

declare(strict_types=1);

namespace Inpsyde\Sniffs\CodeQuality;

use Inpsyde\PhpcsHelpers;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class NoAccessorsSniff implements Sniff
{
    const ALLOWED_NAMES = [
        'getIterator',
        'getInnerIterator',
        'getChildren',
        'setUp',
    ];

    /**
     * @var bool
     */
    public $skipForPrivate = true;

    /**
     * @var bool
     */
    public $skipForProtected = false;

    /**
     * @return int[]
     */
    public function register()
    {
        return [T_FUNCTION];
    }

    /**
     * @param File $file
     * @param int $position
     * @return void
     */
    public function process(File $file, $position)
    {
        if (!PhpcsHelpers::functionIsMethod($file, $position)) {
            return;
        }

        $functionName = $file->getDeclarationName($position);
        if (!$functionName || in_array($functionName, self::ALLOWED_NAMES, true)) {
            return;
        }

        if ($this->skipForPrivate || $this->skipForProtected) {
            $modifierPointerPosition = $file->findPrevious(
                [T_WHITESPACE, T_ABSTRACT],
                $position - 1,
                null,
                true,
                null,
                true
            );

            $modifierPointer = $file->getTokens()[$modifierPointerPosition] ?? null;
            if (!in_array($modifierPointer['code'], Tokens::$scopeModifiers, true)) {
                $modifierPointer = null;
            }

            $modifier = $modifierPointer ? $modifierPointer['code'] ?? null : null;
            if (
                ($modifier === T_PRIVATE && $this->skipForPrivate)
                || ($modifier === T_PROTECTED && $this->skipForProtected)
            ) {
                return;
            }
        }

        preg_match('/^(set|get)[_A-Z0-9]+/', $functionName, $matches);
        if (!$matches) {
            return;
        }

        if ($matches[1] === 'set') {
            $file->addWarning(
                'Setters are discouraged. Try to use immutable objects, constructor injection '
                . 'and for objects that really needs changing state try behavior naming instead, '
                . 'e.g. changeName() instead of setName().',
                $position,
                'NoSetter'
            );

            return;
        }

        $file->addWarning(
            'Getters are discouraged. "Tell Don\'t Ask" principle should be applied if possible, '
            . 'and if getters are really needed consider naming methods after properties, '
            . 'e.g. id() instead of geId().',
            $position,
            'NoGetter'
        );
    }
}
