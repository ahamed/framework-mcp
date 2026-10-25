<?php

namespace Framework\Sniffs\NamingConventions;

use Framework\Sniffs\Support\SniffHelper;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces snake_case method names except PHP-native methods.
 *
 * @since 1.0.0
 */
class SnakeCaseMethodSniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     * @since 1.0.0
     */
    public function register()
    {
        return [T_FUNCTION];
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
        $name_ptr = $phpcs_file->findNext(T_STRING, $stack_ptr + 1);

        if ($name_ptr === false) {
            return;
        }

        $method_name = $tokens[$name_ptr]['content'];

        if (SniffHelper::is_native_method($method_name)) {
            return;
        }

        if (SniffHelper::is_snake_case($method_name)) {
            return;
        }

        $phpcs_file->addError(
            'Method "%s" must use snake_case naming.',
            $name_ptr,
            'NotSnakeCase',
            [$method_name]
        );
    }
}
