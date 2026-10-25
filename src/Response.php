<?php
/**
 * What a tool, resource, or prompt returns from handle().
 * A response only describes content; turning it into the shape the MCP Adapter expects is left
 * to the registrar, because the same text becomes a tool result, a resource content entry, or
 * a prompt message depending on who returned it.
 *
 * @package FrameworkMcp
 * @since   1.0.0
 */
namespace Ahamed\FrameworkMcp;

defined('ABSPATH') || exit;

class Response
{
    /**
     * Plain text content.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const KIND_TEXT = 'text';

    /**
     * An error reported to the client.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const KIND_ERROR = 'error';

    /**
     * Structured content, sent as an object.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const KIND_STRUCTURED = 'structured';

    /**
     * Image bytes.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const KIND_IMAGE = 'image';

    /**
     * Arbitrary binary bytes.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const KIND_BLOB = 'blob';

    /**
     * A resource embedded in a tool result or prompt message.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const KIND_EMBEDDED = 'embedded';

    /**
     * The message author for prompt messages from the user.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const ROLE_USER = 'user';

    /**
     * The message author for prompt messages from the assistant.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const ROLE_ASSISTANT = 'assistant';

    /**
     * The kind of content.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $kind;

    /**
     * The content: text, an error message, structured data, or raw bytes.
     *
     * @var mixed
     *
     * @since 1.0.0
     */
    protected $content;

    /**
     * The MIME type of the content, when it has one.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $mime_type;

    /**
     * The URI of an embedded resource or a binary result.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $uri;

    /**
     * Whether embedded content is binary rather than text.
     *
     * @var bool
     *
     * @since 1.0.0
     */
    protected $binary = false;

    /**
     * The prompt message author.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $role = self::ROLE_USER;

    /**
     * MCP metadata sent as the content's _meta.
     *
     * @var array|null
     *
     * @since 1.0.0
     */
    protected $meta;

    /**
     * Create a response.
     *
     * @param string $kind The kind of content.
     * @param mixed $content The content.
     * @param string|null $mime_type The MIME type.
     * @param string|null $uri The URI.
     * @param bool $binary Whether embedded content is binary.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(
        string $kind,
        $content,
        ?string $mime_type = null,
        ?string $uri = null,
        bool $binary = false
    ) {
        $this->kind = $kind;
        $this->content = $content;
        $this->mime_type = $mime_type;
        $this->uri = $uri;
        $this->binary = $binary;
    }

    /**
     * Respond with plain text.
     *
     * @param string $text The text.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public static function text(string $text)
    {
        return new static(static::KIND_TEXT, $text);
    }

    /**
     * Respond with an error the client is meant to read.
     *
     * @param string $message The message.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public static function error(string $message)
    {
        return new static(static::KIND_ERROR, $message);
    }

    /**
     * Respond with structured content.
     *
     * @param array $data The data.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public static function structured(array $data)
    {
        return new static(static::KIND_STRUCTURED, $data, 'application/json');
    }

    /**
     * Respond with an image.
     *
     * @param string $bytes The raw image bytes, not base64 encoded.
     * @param string $mime_type The image MIME type.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public static function image(string $bytes, string $mime_type = 'image/png')
    {
        return new static(static::KIND_IMAGE, $bytes, $mime_type);
    }

    /**
     * Respond with binary content.
     *
     * A tool or prompt can only carry binary content as an embedded resource, so it needs a URI
     * there; a resource uses its own URI when none is given.
     *
     * @param string $bytes The raw bytes, not base64 encoded.
     * @param string $mime_type The MIME type.
     * @param string|null $uri The URI identifying the content.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public static function blob(string $bytes, string $mime_type = 'application/octet-stream', ?string $uri = null)
    {
        return new static(static::KIND_BLOB, $bytes, $mime_type, $uri, true);
    }

    /**
     * Respond with an embedded resource.
     *
     * @param string $uri The resource URI.
     * @param string $content The text, or raw bytes when binary.
     * @param string|null $mime_type The MIME type.
     * @param bool $binary Whether the content is binary.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public static function embedded(string $uri, string $content, ?string $mime_type = null, bool $binary = false)
    {
        return new static(static::KIND_EMBEDDED, $content, $mime_type, $uri, $binary);
    }

    /**
     * Mark a prompt message as coming from the assistant.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function as_assistant()
    {
        $this->role = static::ROLE_ASSISTANT;

        return $this;
    }

    /**
     * Mark a prompt message as coming from the user.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function as_user()
    {
        $this->role = static::ROLE_USER;

        return $this;
    }

    /**
     * Attach MCP metadata, merged over any attached before.
     *
     * @param array $meta The metadata.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function with_meta(array $meta)
    {
        $this->meta = array_merge($this->meta ?? [], $meta);

        return $this;
    }

    /**
     * Get the kind of content.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function kind()
    {
        return $this->kind;
    }

    /**
     * Get the content.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function content()
    {
        return $this->content;
    }

    /**
     * Get the MIME type.
     *
     * @return string|null
     *
     * @since 1.0.0
     */
    public function mime_type()
    {
        return $this->mime_type;
    }

    /**
     * Get the URI.
     *
     * @return string|null
     *
     * @since 1.0.0
     */
    public function uri()
    {
        return $this->uri;
    }

    /**
     * Determine whether the content is binary.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function is_binary()
    {
        return $this->binary;
    }

    /**
     * Get the prompt message author.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function role()
    {
        return $this->role;
    }

    /**
     * Get the MCP metadata.
     *
     * @return array|null
     *
     * @since 1.0.0
     */
    public function meta()
    {
        return $this->meta;
    }

    /**
     * Determine whether this response reports an error.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function is_error()
    {
        return $this->kind === static::KIND_ERROR;
    }
}
