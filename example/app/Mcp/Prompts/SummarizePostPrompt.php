<?php
/**
 * An MCP prompt that turns a post into a summarizing request, used to exercise prompts/list
 * and prompts/get end to end, including a required argument.
 */
namespace Example\App\Mcp\Prompts;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\Argument;
use Ahamed\FrameworkMcp\Prompt;
use Ahamed\FrameworkMcp\Request;
use Ahamed\FrameworkMcp\Response;

class SummarizePostPrompt extends Prompt
{
    /**
     * What the prompt does, shown to the user picking it.
     *
     * @var string
     */
    protected $description = 'Summarize a post for a newsletter.';

    /**
     * Declare the prompt's arguments. MCP prompt arguments are always strings.
     *
     * @return Argument[]
     */
    public function arguments()
    {
        return [
            new Argument('post_id', 'The ID of the post to summarize', true),
            new Argument('length', 'Number of sentences, 3 when left out'),
        ];
    }

    /**
     * Decide whether the current user may use the prompt.
     *
     * @param Request $request The prompt request.
     *
     * @return bool
     */
    public function permission(Request $request)
    {
        return current_user_can('read_post', (int) $request->get('post_id'));
    }

    /**
     * Build the prompt's messages.
     *
     * @param Request $request The prompt request.
     *
     * @return Response[]
     */
    public function handle(Request $request)
    {
        $post = get_post((int) $request->get('post_id'));

        if (!$post || $post->post_status !== 'publish') {
            return [Response::text('Tell the user that post ' . $request->string('post_id') . ' was not found.')];
        }

        return [
            Response::text('You write short, plain newsletter summaries.')->as_assistant(),
            Response::text(sprintf(
                "Summarize this post in %d sentences.\n\nTitle: %s\n\n%s",
                max(1, (int) ($request->get('length') ?: 3)),
                get_the_title($post),
                wp_strip_all_tags($post->post_content)
            )),
        ];
    }
}
