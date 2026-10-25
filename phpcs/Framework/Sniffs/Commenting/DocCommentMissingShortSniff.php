<?php

namespace Framework\Sniffs\Commenting;

use Framework\Sniffs\Support\SniffHelper;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Requires a short description in docblocks unless @inheritDoc is used.
 *
 * @since 1.0.0
 */
class DocCommentMissingShortSniff implements Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     * @since 1.0.0
     */
    public function register()
    {
        return [T_DOC_COMMENT_OPEN_TAG];
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
        $comment_end = $tokens[$stack_ptr]['comment_closer'] ?? null;

        if ($comment_end === null) {
            return;
        }

        $content = SniffHelper::get_docblock_content($tokens, $stack_ptr);
        $parsed = SniffHelper::parse_docblock($content);

        if (SniffHelper::uses_inherit_doc($parsed)) {
            return;
        }

        $empty = [T_DOC_COMMENT_WHITESPACE, T_DOC_COMMENT_STAR];
        $short = $phpcs_file->findNext($empty, $stack_ptr + 1, $comment_end, true);

        if ($short === false || $tokens[$short]['code'] !== T_DOC_COMMENT_STRING) {
            $phpcs_file->addError(
                'Missing short description in doc comment',
                $stack_ptr,
                'MissingShort'
            );
        }
    }
}
