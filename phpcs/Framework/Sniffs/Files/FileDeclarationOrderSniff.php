<?php

namespace Framework\Sniffs\Files;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Validates the required top-of-file declaration order for class files.
 *
 * @since 1.0.0
 */
class FileDeclarationOrderSniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     * @since 1.0.0
     */
    public function register()
    {
        return [T_OPEN_TAG];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param File $phpcs_file The file being scanned.
     * @param int                        $stack_ptr  The position of the current token.
     *
     * @return void
     * @since 1.0.0
     */
    public function process(File $phpcs_file, $stack_ptr)
    {
        if ($stack_ptr !== 0) {
            return;
        }

        if ($this->find_first_type_declaration($phpcs_file) === false) {
            return;
        }

        $docblock_ptr = $phpcs_file->findNext(T_DOC_COMMENT_OPEN_TAG, $stack_ptr + 1);

        if ($docblock_ptr === false) {
            $phpcs_file->addError(
                'File-level docblock is required before the namespace declaration.',
                $stack_ptr,
                'MissingFileDocblock'
            );

            return;
        }

        $namespace_ptr = $phpcs_file->findNext(T_NAMESPACE, $docblock_ptr + 1);

        if ($namespace_ptr === false) {
            $phpcs_file->addError(
                'Namespace declaration is required after the file-level docblock.',
                $docblock_ptr,
                'MissingNamespace'
            );

            return;
        }

        if ($this->find_abspath_guard($phpcs_file, $namespace_ptr + 1) === false) {
            $phpcs_file->addError(
                'ABSPATH guard "defined(\'ABSPATH\') || exit;" is required after the namespace declaration.',
                $namespace_ptr,
                'MissingAbspathGuard'
            );
        }
    }

    /**
     * Find the first class, interface, or trait declaration.
     *
     * @param File $phpcs_file The file being scanned.
     *
     * @return int|false
     * @since 1.0.0
     */
    protected function find_first_type_declaration(File $phpcs_file)
    {
        $tokens = $phpcs_file->getTokens();
        $type_ptr = $phpcs_file->findNext([T_CLASS, T_INTERFACE, T_TRAIT], 0);

        if ($type_ptr === false) {
            return false;
        }

        if ($tokens[$type_ptr]['code'] === T_CLASS) {
            $previous = $phpcs_file->findPrevious(T_WHITESPACE, $type_ptr - 1, null, true);

            if ($previous !== false && $tokens[$previous]['code'] === T_ANON_CLASS) {
                return $phpcs_file->findNext([T_CLASS, T_INTERFACE, T_TRAIT], $type_ptr + 1);
            }
        }

        return $type_ptr;
    }

    /**
     * Find the ABSPATH guard statement after the namespace.
     *
     * @param File $phpcs_file The file being scanned.
     * @param int                        $start      Start search index.
     *
     * @return int|false
     * @since 1.0.0
     */
    protected function find_abspath_guard(File $phpcs_file, $start)
    {
        $tokens = $phpcs_file->getTokens();
        $defined_ptr = $phpcs_file->findNext(T_STRING, $start, null, false, 'defined');

        while ($defined_ptr !== false) {
            $open_paren = $phpcs_file->findNext(T_OPEN_PARENTHESIS, $defined_ptr + 1);

            if ($open_paren !== false) {
                $string_ptr = $phpcs_file->findNext(T_CONSTANT_ENCAPSED_STRING, $open_paren + 1);

                if ($string_ptr !== false && $tokens[$string_ptr]['content'] === "'ABSPATH'") {
                    $or_ptr = $phpcs_file->findNext([T_LOGICAL_OR, T_BOOLEAN_OR], $string_ptr + 1);

                    if ($or_ptr !== false) {
                        $exit_ptr = $phpcs_file->findNext(T_EXIT, $or_ptr + 1);

                        if ($exit_ptr !== false) {
                            return $defined_ptr;
                        }
                    }
                }
            }

            $defined_ptr = $phpcs_file->findNext(T_STRING, $defined_ptr + 1, null, false, 'defined');
        }

        return false;
    }
}
