<?php

namespace Framework\Sniffs\Commenting;

use Framework\Sniffs\Support\SniffHelper;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Requires every declared property to have a docblock with @var and @since tags.
 *
 * @since 1.0.0
 */
class RequiredPropertyDocblockSniff implements Sniff
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
        if (!$this->is_property_declaration($phpcs_file, $stack_ptr)) {
            return;
        }

        $tokens = $phpcs_file->getTokens();
        $docblock_ptr = SniffHelper::find_preceding_docblock($tokens, $stack_ptr);

        if ($docblock_ptr === null) {
            $phpcs_file->addError(
                'Property "%s" must have a docblock.',
                $stack_ptr,
                'MissingDocblock',
                [$tokens[$stack_ptr]['content']]
            );

            return;
        }

        $content = SniffHelper::get_docblock_content($tokens, $docblock_ptr);
        $parsed = SniffHelper::parse_docblock($content);

        if (!isset($parsed['tags']['var']) || trim($parsed['tags']['var'][0]) === '') {
            $phpcs_file->addError(
                'Property "%s" docblock must include an @var tag.',
                $docblock_ptr,
                'MissingVar',
                [$tokens[$stack_ptr]['content']]
            );
        }

        if (!isset($parsed['tags']['since']) || trim($parsed['tags']['since'][0]) === '') {
            $phpcs_file->addError(
                'Property "%s" docblock must include an @since tag.',
                $docblock_ptr,
                'MissingSince',
                [$tokens[$stack_ptr]['content']]
            );
        }
    }

    /**
     * Determine whether a variable token is a declared property.
     *
     * @param File $phpcs_file The file being scanned.
     * @param int  $stack_ptr  Variable token index.
     *
     * @return bool
     * @since 1.0.0
     */
    protected function is_property_declaration(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->getTokens();
        $previous = $phpcs_file->findPrevious(T_WHITESPACE, $stack_ptr - 1, null, true);

        if ($previous === false) {
            return false;
        }

        $property_tokens = [
            T_PUBLIC,
            T_PROTECTED,
            T_PRIVATE,
            T_STATIC,
            T_VAR,
            T_STRING,
        ];

        while ($previous !== false && in_array($tokens[$previous]['code'], $property_tokens, true)) {
            if (in_array($tokens[$previous]['code'], [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_VAR], true)) {
                return true;
            }

            $previous = $phpcs_file->findPrevious(T_WHITESPACE, $previous - 1, null, true);
        }

        return false;
    }
}
