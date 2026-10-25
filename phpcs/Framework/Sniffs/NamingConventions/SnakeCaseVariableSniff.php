<?php

namespace Framework\Sniffs\NamingConventions;

use Framework\Sniffs\Support\SniffHelper;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces snake_case variable names for properties and local variables.
 *
 * @since 1.0.0
 */
class SnakeCaseVariableSniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     * @since 1.0.0
     */
    public function register()
    {
        return [T_VARIABLE];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param File $phpcs_file The file being scanned.
     * @param int  $stack_ptr  The position of the current token.
     *
     * @return void
     * @since 1.0.0
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->getTokens();
        $variable_name = ltrim($tokens[$stack_ptr]['content'], '$');

        if ($variable_name === 'this') {
            return;
        }

        if (SniffHelper::is_snake_case($variable_name)) {
            return;
        }

        $phpcs_file->addError(
            'Variable "$%s" must use snake_case naming.',
            $stack_ptr,
            'NotSnakeCase',
            [$variable_name]
        );
    }
}
