<?php
/**
 * The arguments of one MCP tool call, resource read, or prompt fetch.
 * Deliberately not the HTTP request: an ability runs the same over REST and over WP-CLI STDIO,
 * where cookies, sessions, and route parameters do not exist.
 *
 * @package FrameworkMcp
 * @since   1.0.0
 */
namespace Ahamed\FrameworkMcp;

defined('ABSPATH') || exit;

use Framework\Exceptions\ValidationException;
use Framework\Supports\Arr;
use Framework\Validation\Validator;

use function Framework\user;

class Request
{
    /**
     * The arguments the client sent.
     *
     * @var array
     *
     * @since 1.0.0
     */
    protected $arguments = [];

    /**
     * The arguments that passed the last validate() call.
     *
     * @var array
     *
     * @since 1.0.0
     */
    protected $validated = [];

    /**
     * Create a request for the given arguments.
     *
     * @param array $arguments The arguments the client sent.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(array $arguments = [])
    {
        $this->arguments = $arguments;
    }

    /**
     * Get every argument.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function all()
    {
        return $this->arguments;
    }

    /**
     * Get one argument, reading dot notation for nested values.
     *
     * @param string $key The argument name.
     * @param mixed $default The value when the argument is absent.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function get(string $key, $default = null)
    {
        return Arr::get($this->arguments, $key, $default);
    }

    /**
     * Determine whether an argument was sent.
     *
     * @param string $key The argument name.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function has(string $key)
    {
        $missing = new \stdClass();

        return Arr::get($this->arguments, $key, $missing) !== $missing;
    }

    /**
     * Get only the named arguments.
     *
     * @param array $keys The argument names.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function only(array $keys)
    {
        return array_intersect_key($this->arguments, array_flip($keys));
    }

    /**
     * Get every argument except the named ones.
     *
     * @param array $keys The argument names.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function except(array $keys)
    {
        return array_diff_key($this->arguments, array_flip($keys));
    }

    /**
     * Get an argument as a string.
     *
     * @param string $key The argument name.
     * @param string $default The value when the argument is absent.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function string(string $key, string $default = '')
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Get an argument as an integer.
     *
     * @param string $key The argument name.
     * @param int $default The value when the argument is absent.
     *
     * @return int
     *
     * @since 1.0.0
     */
    public function integer(string $key, int $default = 0)
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get an argument as a boolean, reading "true", "1", "yes", and "on" as true.
     *
     * @param string $key The argument name.
     * @param bool $default The value when the argument is absent.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function boolean(string $key, bool $default = false)
    {
        if (!$this->has($key)) {
            return $default;
        }

        return filter_var($this->get($key), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Validate the arguments against framework validation rules.
     *
     * A failure is reported to the client as a tool error carrying the validation messages.
     *
     * @param array $rules The rules keyed by argument name.
     *
     * @return array The validated arguments.
     *
     * @throws ValidationException When an argument fails its rules.
     *
     * @since 1.0.0
     */
    public function validate(array $rules)
    {
        $this->validated = Validator::make($this->arguments, $rules)->validate();

        return $this->validated;
    }

    /**
     * Get the arguments that passed the last validate() call.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function validated()
    {
        return $this->validated;
    }

    /**
     * Get the current user.
     *
     * @return \Framework\Wordpress\User
     *
     * @since 1.0.0
     */
    public function user()
    {
        return user();
    }

    /**
     * Get the identifier of the current user, if there is one.
     *
     * @return int|null
     *
     * @since 1.0.0
     */
    public function user_id()
    {
        $id = (int) get_current_user_id();

        return $id > 0 ? $id : null;
    }
}
