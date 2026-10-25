<?php

namespace Framework\Sniffs\Commenting;

use Framework\Sniffs\Support\SniffHelper;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Requires every method to have a docblock.
 *
 * @since 1.0.0
 */
class RequiredMethodDocblockSniff implements Sniff
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
        $docblock_ptr = SniffHelper::find_preceding_docblock($tokens, $stack_ptr);

        if ($docblock_ptr !== null) {
            return;
        }

        $name_ptr = $phpcs_file->findNext(T_STRING, $stack_ptr + 1);
        $method_name = $name_ptr !== false ? $tokens[$name_ptr]['content'] : 'unknown';

        $phpcs_file->addError(
            'Method "%s" must have a docblock.',
            $stack_ptr,
            'MissingDocblock',
            [$method_name]
        );
    }
}
