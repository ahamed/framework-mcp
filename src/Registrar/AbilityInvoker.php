<?php
/**
 * Runs a component's handle(), permission(), and should_register() for the ability callbacks.
 * Exceptions are caught here, before WordPress and the adapter see them, because both would
 * hand the raw exception message to the client; this applies the same disclosure rule as the
 * framework's API exception handler instead.
 *
 * @package    FrameworkMcp
 * @subpackage Registrar
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Registrar;

defined('ABSPATH') || exit;

use Framework\Concerns\DependencyResolvable;
use Framework\Exceptions\HttpException;
use Framework\Exceptions\ValidationException;
use Ahamed\FrameworkMcp\Ability;
use Ahamed\FrameworkMcp\Request;
use Framework\Supports\Facades\Log;
use LogicException;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;
use WP_Error;

class AbilityInvoker
{
    use DependencyResolvable;

    /**
     * The message a client receives when the real one must not be disclosed.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const GENERIC_MESSAGE = 'The request could not be completed.';

    /**
     * The WP_Error code a caught exception is reported under.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const ERROR_CODE = 'mcp_request_failed';

    /**
     * Converts handle() results to the adapter's shapes.
     *
     * @var ResponseMapper
     *
     * @since 1.0.0
     */
    protected $mapper;

    /**
     * Create the invoker.
     *
     * @param ResponseMapper $mapper Converts handle() results.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(ResponseMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Run the component's handle() for the ability's execute callback.
     *
     * @param Ability $ability The component.
     * @param mixed $input The input WordPress validated against the input schema.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function execute(Ability $ability, $input = null)
    {
        try {
            if (!method_exists($ability, 'handle')) {
                throw new LogicException(sprintf('%s must define a handle() method.', get_class($ability)));
            }

            $result = $this->call($ability, 'handle', [$this->request($input)]);

            return $this->mapper->map($ability, $result);
        } catch (Throwable $exception) {
            return $this->failure($ability, $exception);
        }
    }

    /**
     * Run the component's permission() for the ability's permission callback.
     *
     * A component without permission() requires the read capability, the same check the
     * adapter's default transport gate applies, so it is safe on every exposure channel.
     *
     * @param Ability $ability The component.
     * @param mixed $input The input.
     *
     * @return bool|WP_Error
     *
     * @since 1.0.0
     */
    public function permission(Ability $ability, $input = null)
    {
        try {
            if (!method_exists($ability, 'permission')) {
                return current_user_can('read');
            }

            $result = $this->call($ability, 'permission', [$this->request($input)]);

            return $result instanceof WP_Error ? $result : (bool) $result;
        } catch (Throwable $exception) {
            return $this->failure($ability, $exception);
        }
    }

    /**
     * Run the component's should_register() for the current MCP request.
     *
     * A failing check hides the component, so a broken condition cannot expose it.
     *
     * @param Ability $ability The component.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function should_register(Ability $ability)
    {
        if (!method_exists($ability, 'should_register')) {
            return true;
        }

        try {
            return (bool) $this->call($ability, 'should_register', [new Request()]);
        } catch (Throwable $exception) {
            $this->report($ability, $exception);

            return false;
        }
    }

    /**
     * Call a method, injecting the given objects by type and resolving every other class typed
     * parameter from the container.
     *
     * @param object $instance The object.
     * @param string $method The method name.
     * @param array $objects The objects available to inject, such as the request.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function call($instance, string $method, array $objects = [])
    {
        $arguments = $objects;

        foreach ((new ReflectionMethod($instance, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $arguments[] = $type->getName();
            }
        }

        return $instance->$method(...$this->resolve_method_dependencies($instance, $method, $arguments));
    }

    /**
     * Wrap ability input in a request.
     *
     * @param mixed $input The input.
     *
     * @return Request
     *
     * @since 1.0.0
     */
    protected function request($input)
    {
        return new Request(is_array($input) ? $input : []);
    }

    /**
     * Log an exception and turn it into the error the client may see.
     *
     * @param Ability $ability The component that threw.
     * @param Throwable $exception The exception.
     *
     * @return WP_Error
     *
     * @since 1.0.0
     */
    protected function failure(Ability $ability, Throwable $exception)
    {
        $this->report($ability, $exception);

        return new WP_Error(static::ERROR_CODE, $this->public_message($exception));
    }

    /**
     * Write an exception to the framework log.
     *
     * @param Ability $ability The component that threw.
     * @param Throwable $exception The exception.
     *
     * @return void
     *
     * @since 1.0.0
     */
    protected function report(Ability $ability, Throwable $exception)
    {
        Log::error(sprintf(
            '[mcp] %s (%s) failed: %s: %s in %s:%d',
            get_class($ability),
            $ability::KIND,
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));
    }

    /**
     * Decide what an exception may tell the client.
     *
     * Validation messages and client-side HTTP errors are written for the caller. Anything else
     * may carry paths, queries, or credentials, so it is withheld unless WP_DEBUG is on.
     *
     * @param Throwable $exception The exception.
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function public_message(Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            return $this->validation_message($exception);
        }

        if ($exception instanceof HttpException && $exception->get_status() < 500) {
            return $exception->getMessage();
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            return $exception->getMessage();
        }

        return static::GENERIC_MESSAGE;
    }

    /**
     * Join the first message of every failed field.
     *
     * @param ValidationException $exception The exception.
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function validation_message(ValidationException $exception)
    {
        $messages = [];

        foreach ((array) $exception->get_errors() as $errors) {
            $first = is_array($errors) ? reset($errors) : $errors;

            if (is_string($first) && $first !== '') {
                $messages[] = $first;
            }
        }

        return empty($messages) ? $exception->getMessage() : implode(' ', $messages);
    }
}
