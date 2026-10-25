<?php

namespace Framework\Sniffs\Commenting;

use Framework\Sniffs\Support\SniffHelper;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Validates the required file-level docblock structure.
 *
 * @since 1.0.0
 */
class FileDocblockSniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     * @since 1.0.0
     */
    public function register()
    {
        return [T_NAMESPACE];
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
        if ($stack_ptr !== $phpcs_file->findNext(T_NAMESPACE, 0)) {
            return;
        }

        $tokens = $phpcs_file->getTokens();
        $docblock_ptr = SniffHelper::find_preceding_docblock($tokens, $stack_ptr);

        if ($docblock_ptr === null) {
            $phpcs_file->addError(
                'File-level docblock is required before the namespace declaration.',
                $stack_ptr,
                'MissingFileDocblock'
            );

            return;
        }

        $content = SniffHelper::get_docblock_content($tokens, $docblock_ptr);
        $parsed = SniffHelper::parse_docblock($content);

        if ($parsed['description'] === '') {
            $phpcs_file->addError(
                'File-level docblock must include a description.',
                $docblock_ptr,
                'MissingDescription'
            );
        }

        if (!isset($parsed['tags']['package']) || trim($parsed['tags']['package'][0]) === '') {
            $phpcs_file->addError(
                'File-level docblock must include an @package tag.',
                $docblock_ptr,
                'MissingPackage'
            );
        }

        if (!isset($parsed['tags']['since']) || trim($parsed['tags']['since'][0]) === '') {
            $phpcs_file->addError(
                'File-level docblock must include an @since tag.',
                $docblock_ptr,
                'MissingSince'
            );
        }

        $namespace = $this->get_namespace($phpcs_file, $stack_ptr);
        $segments = explode('\\', $namespace);

        if (count($segments) > 2) {
            $has_subpackage = isset($parsed['tags']['subpackage'])
                && trim($parsed['tags']['subpackage'][0]) !== '';

            if (!$has_subpackage) {
                $phpcs_file->addError(
                    'File-level docblock must include an @subpackage tag for nested namespaces.',
                    $docblock_ptr,
                    'MissingSubpackage'
                );
            }
        }
    }

    /**
     * Extract the namespace string from a namespace token.
     *
     * @param File $phpcs_file The file being scanned.
     * @param int  $stack_ptr  Namespace token index.
     *
     * @return string
     * @since 1.0.0
     */
    protected function get_namespace(File $phpcs_file, $stack_ptr)
    {
        $tokens = $phpcs_file->getTokens();
        $parts = [];
        $current = $stack_ptr + 1;

        while ($current < count($tokens)) {
            $code = $tokens[$current]['code'];

            if ($code === T_SEMICOLON || $code === T_OPEN_CURLY_BRACKET) {
                break;
            }

            if ($code === T_STRING || $code === T_NS_SEPARATOR) {
                $parts[] = $tokens[$current]['content'];
            }

            ++$current;
        }

        return implode('', $parts);
    }
}
