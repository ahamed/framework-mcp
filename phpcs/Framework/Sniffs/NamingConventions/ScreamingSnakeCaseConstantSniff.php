<?php

namespace Framework\Sniffs\NamingConventions;

use Framework\Sniffs\Support\SniffHelper;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces SCREAMING_SNAKE_CASE constant names.
 *
 * @since 1.0.0
 */
class ScreamingSnakeCaseConstantSniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     * @since 1.0.0
     */
    public function register()
    {
        return [T_CONST, T_CLASS, T_INTERFACE, T_TRAIT];
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
        $token_code = $tokens[$stack_ptr]['code'];

        if ($token_code === T_CONST) {
            $name_ptr = $phpcs_file->findNext(T_STRING, $stack_ptr + 1);
            $this->validate_constant_name($phpcs_file, $name_ptr, $tokens);

            return;
        }

        $scope_opener = $tokens[$stack_ptr]['scope_opener'] ?? null;
        $scope_closer = $tokens[$stack_ptr]['scope_closer'] ?? null;

        if ($scope_opener === null || $scope_closer === null) {
            return;
        }

        $const_ptr = $scope_opener;

        while (($const_ptr = $phpcs_file->findNext(T_CONST, $const_ptr + 1, $scope_closer)) !== false) {
            $name_ptr = $phpcs_file->findNext(T_STRING, $const_ptr + 1);

            if ($name_ptr === false || $name_ptr > $scope_closer) {
                continue;
            }

            $this->validate_constant_name($phpcs_file, $name_ptr, $tokens);
        }
    }

    /**
     * Validate a constant identifier uses SCREAMING_SNAKE_CASE.
     *
     * @param File        $phpcs_file The file being scanned.
     * @param int|false   $name_ptr   Constant name token index.
     * @param array       $tokens     File tokens.
     *
     * @return void
     * @since 1.0.0
     */
    protected function validate_constant_name(File $phpcs_file, $name_ptr, array $tokens)
    {
        if ($name_ptr === false) {
            return;
        }

        $constant_name = $tokens[$name_ptr]['content'];

        if (SniffHelper::is_screaming_snake_case($constant_name)) {
            return;
        }

        $phpcs_file->addError(
            'Constant "%s" must use SCREAMING_SNAKE_CASE naming.',
            $name_ptr,
            'NotScreamingSnakeCase',
            [$constant_name]
        );
    }
}
