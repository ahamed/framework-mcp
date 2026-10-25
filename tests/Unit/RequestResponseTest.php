<?php

namespace Ahamed\FrameworkMcp\Tests\Unit;

use Framework\Exceptions\ValidationException;
use Ahamed\FrameworkMcp\Argument;
use Ahamed\FrameworkMcp\Request;
use Ahamed\FrameworkMcp\Response;
use Ahamed\FrameworkMcp\Tests\Unit\TestCase;

class RequestResponseTest extends TestCase
{
    protected function tearDown(): void
    {
        $GLOBALS['framework_test_current_user_id'] = 0;

        parent::tearDown();
    }

    public function test_reads_arguments_with_defaults(): void
    {
        $request = new Request(['units' => 'metric', 'days' => '3', 'geo' => ['lat' => 1.5]]);

        $this->assertSame('metric', $request->get('units', 'imperial'));
        $this->assertSame('imperial', $request->get('missing', 'imperial'));
        $this->assertSame(1.5, $request->get('geo.lat'));
        $this->assertSame(3, $request->integer('days'));
        $this->assertSame('3', $request->string('days'));
        $this->assertSame(['units' => 'metric', 'days' => '3', 'geo' => ['lat' => 1.5]], $request->all());
    }

    public function test_presence_and_subsets(): void
    {
        $request = new Request(['a' => null, 'b' => 2, 'c' => 3]);

        $this->assertTrue($request->has('a'));
        $this->assertFalse($request->has('z'));
        $this->assertSame(['b' => 2], $request->only(['b']));
        $this->assertSame(['a' => null], $request->except(['b', 'c']));
    }

    public function test_boolean_reads_common_truthy_strings(): void
    {
        $request = new Request(['on' => 'yes', 'off' => '0']);

        $this->assertTrue($request->boolean('on'));
        $this->assertFalse($request->boolean('off'));
        $this->assertTrue($request->boolean('missing', true));
    }

    public function test_validate_returns_validated_subset(): void
    {
        $request = new Request(['location' => 'Dhaka', 'extra' => 'x']);

        $this->assertSame(['location' => 'Dhaka'], $request->validate(['location' => 'required|string']));
        $this->assertSame(['location' => 'Dhaka'], $request->validated());
    }

    public function test_validate_throws_on_failure(): void
    {
        $this->expectException(ValidationException::class);

        (new Request([]))->validate(['location' => 'required']);
    }

    public function test_user_id_is_null_for_guests(): void
    {
        $this->assertNull((new Request())->user_id());

        $GLOBALS['framework_test_current_user_id'] = 7;

        $this->assertSame(7, (new Request())->user_id());
    }

    public function test_response_constructors(): void
    {
        $this->assertSame(Response::KIND_TEXT, Response::text('Sunny')->kind());
        $this->assertTrue(Response::error('No')->is_error());
        $this->assertSame(['t' => 1], Response::structured(['t' => 1])->content());

        $image = Response::image('bytes', 'image/webp');
        $this->assertSame('image/webp', $image->mime_type());

        $embedded = Response::embedded('file://a.txt', 'hello', 'text/plain');
        $this->assertSame('file://a.txt', $embedded->uri());
        $this->assertFalse($embedded->is_binary());
        $this->assertTrue(Response::blob('bytes')->is_binary());
    }

    public function test_response_roles_and_meta(): void
    {
        $response = Response::text('Hi')->as_assistant()->with_meta(['a' => 1])->with_meta(['b' => 2]);

        $this->assertSame(Response::ROLE_ASSISTANT, $response->role());
        $this->assertSame(['a' => 1, 'b' => 2], $response->meta());
        $this->assertSame(Response::ROLE_USER, Response::text('Hi')->role());
    }

    public function test_argument_value_object(): void
    {
        $argument = new Argument('tone', 'The tone', true);

        $this->assertSame('tone', $argument->name());
        $this->assertSame('The tone', $argument->description());
        $this->assertTrue($argument->is_required());
    }
}
