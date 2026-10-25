<?php

namespace Framework\Sniffs\Commenting;

use Framework\Sniffs\Support\SniffHelper;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Validates method docblock tag order, spacing, and required tags.
 *
 * @since 1.0.0
 */
class DocblockTagOrderSniff implements Sniff
{
    /**
     * Expected tag order for method docblocks.
     *
     * @var string[]
     * @since 1.0.0
     */
    protected $tag_order = ['template', 'param', 'return', 'throws', 'since'];

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

        if ($docblock_ptr === null) {
            return;
        }

        $content = SniffHelper::get_docblock_content($tokens, $docblock_ptr);
        $parsed = SniffHelper::parse_docblock($content);
        $body_lines = SniffHelper::get_docblock_body_lines($tokens, $docblock_ptr);

        if (SniffHelper::uses_inherit_doc($parsed)) {
            return;
        }

        if ($parsed['description'] === '') {
            $phpcs_file->addError(
                'Method docblock must include a description.',
                $docblock_ptr,
                'MissingDescription'
            );
        }

        $open_paren = $tokens[$stack_ptr]['parenthesis_opener'] ?? null;
        $close_paren = $tokens[$stack_ptr]['parenthesis_closer'] ?? null;
        $has_parameters = false;

        if ($open_paren !== null && $close_paren !== null) {
            $has_parameters = $phpcs_file->findNext(T_VARIABLE, $open_paren + 1, $close_paren) !== false;
        }

        if ($has_parameters) {
            if (!isset($parsed['tags']['param']) || empty($parsed['tags']['param'])) {
                $phpcs_file->addError(
                    'Method docblock must include @param tags for all parameters.',
                    $docblock_ptr,
                    'MissingParam'
                );
            } else {
                foreach ($parsed['tags']['param'] as $param_tag) {
                    if (!SniffHelper::is_valid_param_tag($param_tag)) {
                        $phpcs_file->addError(
                            'Each @param tag must include a type, variable name, and description.',
                            $docblock_ptr,
                            'InvalidParamDescription'
                        );

                        break;
                    }
                }
            }
        }

        if (!isset($parsed['tags']['return'])) {
            $phpcs_file->addError(
                'Method docblock must include an @return tag.',
                $docblock_ptr,
                'MissingReturn'
            );
        }

        if (!isset($parsed['tags']['since']) || trim($parsed['tags']['since'][0]) === '') {
            $phpcs_file->addError(
                'Method docblock must include an @since tag.',
                $docblock_ptr,
                'MissingSince'
            );
        }

        $this->validate_tag_order($phpcs_file, $docblock_ptr, $body_lines);
        $this->validate_blank_line_separators($phpcs_file, $docblock_ptr, $body_lines, $parsed);
    }

    /**
     * Validate docblock tags appear in the required order.
     *
     * @param File  $phpcs_file   The file being scanned.
     * @param int   $docblock_ptr Docblock open tag index.
     * @param array $body_lines   Docblock body lines.
     *
     * @return void
     * @since 1.0.0
     */
    protected function validate_tag_order(File $phpcs_file, $docblock_ptr, array $body_lines)
    {
        $positions = [];

        foreach ($body_lines as $index => $line) {
            if (preg_match('/@(\S+)/', $line, $matches)) {
                $tag = strtolower($matches[1]);
                $positions[$tag][] = $index;
            }
        }

        $last_position = -1;

        foreach ($this->tag_order as $tag) {
            if (!isset($positions[$tag])) {
                continue;
            }

            $position = $positions[$tag][0];

            if ($position < $last_position) {
                $phpcs_file->addError(
                    'Docblock tags must appear in order: @template, @param, @return, @throws, @since.',
                    $docblock_ptr,
                    'InvalidTagOrder'
                );

                return;
            }

            $last_position = $position;
        }
    }

    /**
     * Validate blank lines separate docblock sections.
     *
     * @param File  $phpcs_file   The file being scanned.
     * @param int   $docblock_ptr Docblock open tag index.
     * @param array $body_lines   Docblock body lines.
     * @param array $parsed       Parsed docblock data.
     *
     * @return void
     * @since 1.0.0
     */
    protected function validate_blank_line_separators(File $phpcs_file, $docblock_ptr, array $body_lines, array $parsed)
    {
        $first_tag_line = null;
        $tag_first_lines = [];

        foreach ($body_lines as $index => $line) {
            if (preg_match('/@(\S+)/', $line, $matches)) {
                $tag = strtolower($matches[1]);

                if ($first_tag_line === null) {
                    $first_tag_line = $index;
                }

                if (!isset($tag_first_lines[$tag])) {
                    $tag_first_lines[$tag] = $index;
                }
            }
        }

        if ($first_tag_line !== null && $parsed['description'] !== '') {
            if (!$this->has_blank_line_before($body_lines, $first_tag_line)) {
                $phpcs_file->addError(
                    'Method docblock must include a blank line between the description and tag block.',
                    $docblock_ptr,
                    'MissingBlankLineAfterDescription'
                );
            }
        }

        $groups = ['template', 'param', 'return', 'throws', 'since'];
        $previous_group_line = null;

        foreach ($groups as $group) {
            if (!isset($tag_first_lines[$group])) {
                continue;
            }

            if ($previous_group_line !== null) {
                if (!$this->has_blank_line_before($body_lines, $tag_first_lines[$group])) {
                    $phpcs_file->addError(
                        'Method docblock must include blank lines between tag groups.',
                        $docblock_ptr,
                        'MissingBlankLineBetweenTags'
                    );

                    return;
                }
            }

            $previous_group_line = $tag_first_lines[$group];
        }
    }

    /**
     * Determine whether a blank docblock line precedes a given line index.
     *
     * @param array $body_lines Docblock body lines.
     * @param int   $line_index Target line index.
     *
     * @return bool
     * @since 1.0.0
     */
    protected function has_blank_line_before(array $body_lines, $line_index)
    {
        if ($line_index === 0) {
            return false;
        }

        $previous_index = $line_index - 1;

        while ($previous_index >= 0) {
            $line = trim($body_lines[$previous_index], " \t*");

            if ($line === '') {
                return true;
            }

            --$previous_index;
        }

        return false;
    }
}
