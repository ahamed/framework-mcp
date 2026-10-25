<?php
/**
 * Converts what handle() returns into the value the MCP Adapter turns into protocol content.
 * The same response means different shapes per component: a tool result is a single content
 * block, a resource returns a list of contents keyed by URI, and a prompt returns messages.
 * The adapter's expected shapes are what this class encodes, so it is the place to change
 * when the adapter changes them.
 *
 * @package    FrameworkMcp
 * @subpackage Registrar
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Registrar;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\Ability;
use Ahamed\FrameworkMcp\Prompt;
use Ahamed\FrameworkMcp\Resource;
use Ahamed\FrameworkMcp\Response;
use LogicException;
use WP_Error;

class ResponseMapper
{
    /**
     * The WP_Error code an error response is reported under.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const ERROR_CODE = 'mcp_error_response';

    /**
     * Map a handle() result for the given component.
     *
     * @param Ability $ability The component that produced the result.
     * @param mixed $result The value handle() returned.
     *
     * @return mixed
     *
     * @throws LogicException When the result cannot be expressed for the component.
     *
     * @since 1.0.0
     */
    public function map(Ability $ability, $result)
    {
        if ($result instanceof WP_Error) {
            return $result;
        }

        $responses = $this->responses($result);

        if ($ability instanceof Resource) {
            return $responses === null ? $result : $this->resource($ability, $responses);
        }

        if ($ability instanceof Prompt) {
            if ($responses === null && is_string($result)) {
                $responses = [Response::text($result)];
            }

            return $responses === null ? $result : $this->prompt($responses);
        }

        return $responses === null ? $result : $this->tool($responses);
    }

    /**
     * Read a result as a list of responses, or null when it is a plain value.
     *
     * @param mixed $result The value handle() returned.
     *
     * @return Response[]|null
     *
     * @since 1.0.0
     */
    protected function responses($result)
    {
        if ($result instanceof Response) {
            return [$result];
        }

        if (!is_array($result) || empty($result)) {
            return null;
        }

        foreach ($result as $item) {
            if (!$item instanceof Response) {
                return null;
            }
        }

        return array_values($result);
    }

    /**
     * Map responses to a tool result, which the adapter renders as exactly one content block.
     *
     * @param Response[] $responses The responses.
     *
     * @return mixed
     *
     * @throws LogicException When more than one response is returned.
     *
     * @since 1.0.0
     */
    protected function tool(array $responses)
    {
        if (count($responses) > 1) {
            throw new LogicException('A tool result carries one content block; return a single Response.');
        }

        $response = $responses[0];

        switch ($response->kind()) {
            case Response::KIND_ERROR:
                return $this->error($response);
            case Response::KIND_IMAGE:
                return $this->filter([
                    'type' => 'image',
                    'results' => $response->content(),
                    'mimeType' => $response->mime_type(),
                    '_meta' => $response->meta(),
                ]);
            case Response::KIND_BLOB:
            case Response::KIND_EMBEDDED:
                return [
                    'type' => 'resource',
                    'resource' => $this->contents($response, $this->required_uri($response)),
                ];
            default:
                return $response->content();
        }
    }

    /**
     * Map responses to resource contents.
     *
     * @param Resource $ability The resource.
     * @param Response[] $responses The responses.
     *
     * @return array|WP_Error
     *
     * @since 1.0.0
     */
    protected function resource(Resource $ability, array $responses)
    {
        $contents = [];

        foreach ($responses as $response) {
            if ($response->is_error()) {
                return $this->error($response);
            }

            $entry = $this->contents($response, $response->uri() ?? (string) $ability->uri());

            if (!isset($entry['mimeType']) && $ability->mime_type() !== null) {
                $entry['mimeType'] = $ability->mime_type();
            }

            $contents[] = $entry;
        }

        return $contents;
    }

    /**
     * Map responses to prompt messages.
     *
     * @param Response[] $responses The responses.
     *
     * @return array|WP_Error
     *
     * @since 1.0.0
     */
    protected function prompt(array $responses)
    {
        $messages = [];

        foreach ($responses as $response) {
            if ($response->is_error()) {
                return $this->error($response);
            }

            $messages[] = [
                'role' => $response->role(),
                'content' => $this->prompt_content($response),
            ];
        }

        return ['messages' => $messages];
    }

    /**
     * Map one response to a prompt message content block.
     *
     * @param Response $response The response.
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function prompt_content(Response $response)
    {
        switch ($response->kind()) {
            case Response::KIND_IMAGE:
                $content = [
                    'type' => 'image',
                    'data' => base64_encode($response->content()),
                    'mimeType' => $response->mime_type(),
                ];
                break;
            case Response::KIND_BLOB:
            case Response::KIND_EMBEDDED:
                $content = [
                    'type' => 'resource',
                    'resource' => $this->contents($response, $this->required_uri($response)),
                ];
                break;
            default:
                $content = ['type' => 'text', 'text' => $this->text($response)];
        }

        $content['_meta'] = $response->meta();

        return $this->filter($content);
    }

    /**
     * Build one resource contents entry, base64 encoding binary content.
     *
     * The response's metadata goes on the contents, so for embedded results it describes the
     * resource rather than the block around it.
     *
     * @param Response $response The response.
     * @param string $uri The URI to file the content under.
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function contents(Response $response, string $uri)
    {
        $binary = $response->is_binary() || $response->kind() === Response::KIND_IMAGE;

        return $this->filter([
            'uri' => $uri,
            'mimeType' => $response->mime_type(),
            'text' => $binary ? null : $this->text($response),
            'blob' => $binary ? base64_encode($response->content()) : null,
            '_meta' => $response->meta(),
        ]);
    }

    /**
     * Read a response's content as text, encoding structured content as JSON.
     *
     * @param Response $response The response.
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function text(Response $response)
    {
        $content = $response->content();

        return is_string($content) ? $content : (string) wp_json_encode($content);
    }

    /**
     * Get the URI an embedded or binary response must carry outside a resource.
     *
     * @param Response $response The response.
     *
     * @return string
     *
     * @throws LogicException When the response has no URI.
     *
     * @since 1.0.0
     */
    protected function required_uri(Response $response)
    {
        if (empty($response->uri())) {
            throw new LogicException(
                'Binary and embedded content outside a resource needs a URI; '
                . 'pass one to Response::blob() or use Response::embedded().'
            );
        }

        return $response->uri();
    }

    /**
     * Turn an error response into the WP_Error the adapter reports as a failed result.
     *
     * @param Response $response The error response.
     *
     * @return WP_Error
     *
     * @since 1.0.0
     */
    protected function error(Response $response)
    {
        return new WP_Error(static::ERROR_CODE, (string) $response->content());
    }

    /**
     * Drop null entries, which the adapter would otherwise send as explicit nulls.
     *
     * @param array $values The values.
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function filter(array $values)
    {
        return array_filter($values, function ($value) {
            return $value !== null;
        });
    }
}
