<?php

namespace Framework\Sniffs\Visibility;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Disallows private methods and properties.
 *
 * @since 1.0.0
 */
class NoPrivateSniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     * @since 1.0.0
     */
    public function register()
    {
        return [T_PRIVATE];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer\Files\File $phpcs_file The file being scanned.
     * @param int                        $stack_ptr  The position of the current token.
     *
     * @return void
     * @since 1.0.0
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $fix = $phpcs_file->addFixableError(
            'Private members are not allowed; use protected or public instead.',
            $stack_ptr,
            'PrivateNotAllowed'
        );

        if ($fix !== true) {
            return;
        }

        $phpcs_file->fixer->replaceToken($stack_ptr, 'protected');
    }
}
