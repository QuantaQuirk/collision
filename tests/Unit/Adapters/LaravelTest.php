<?php

declare(strict_types=1);

namespace Tests\Unit\Adapters;

use Exception;
use QuantaQuirk\Container\Container;
use QuantaQuirk\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use QuantaQuirk\Foundation\Application;
use NunoMaduro\Collision\Adapters\QuantaQuirk\CollisionServiceProvider;
use NunoMaduro\Collision\Adapters\QuantaQuirk\ExceptionHandler;
use NunoMaduro\Collision\Adapters\QuantaQuirk\Inspector;
use NunoMaduro\Collision\Provider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Output\BufferedOutput;

class QuantaQuirkTest extends TestCase
{
    /** @test */
    public function itIsRegisteredOnArtisan(): void
    {
        $app = $this->createApplication();
        $app->method('runningInConsole')->willReturn(true);
        $app->method('runningUnitTests')->willReturn(false);

        (new CollisionServiceProvider($app))->register();

        $this->assertInstanceOf(ExceptionHandler::class, $app->make(ExceptionHandlerContract::class));
    }

    /** @test */
    public function itIsNotRegisteredOnTesting(): void
    {
        $app = $this->createApplication();
        $app->method('runningInConsole')->willReturn(true);
        $app->method('runningUnitTests')->willReturn(true);

        (new CollisionServiceProvider($app))->register();

        $this->assertNotInstanceOf(ExceptionHandler::class, $app->make(ExceptionHandlerContract::class));
    }

    /** @test */
    public function itIsNotRegisteredOnHttp(): void
    {
        $app = $this->createApplication();
        $app->method('runningInConsole')->willReturn(false);
        $app->method('runningUnitTests')->willReturn(false);

        (new CollisionServiceProvider($app))->register();

        $this->assertNotInstanceOf(ExceptionHandler::class, $app->make(ExceptionHandlerContract::class));
    }

    /** @test */
    public function exceptionHandlerRespectsIsContract(): void
    {
        $app = $this->createApplication();

        $this->assertInstanceOf(
            ExceptionHandlerContract::class,
            new ExceptionHandler($app, $app->make(ExceptionHandlerContract::class))
        );
    }

    /** @test */
    public function itReportsToTheOriginalExceptionHandler(): void
    {
        $app = $this->createApplication();
        $exception = new Exception();
        $originalExceptionHandlerMock = $this->createMock(ExceptionHandlerContract::class);
        $originalExceptionHandlerMock->expects($this->once())->method('report')->with($exception);

        $exceptionHandler = new ExceptionHandler($app, $originalExceptionHandlerMock);
        $exceptionHandler->report($exception);
    }

    /** @test */
    public function itRendersToTheOriginalExceptionHandler(): void
    {
        $app = $this->createApplication();
        $exception = new Exception();
        $request = new \stdClass();
        $originalExceptionHandlerMock = $this->createMock(ExceptionHandlerContract::class);
        $originalExceptionHandlerMock->expects($this->once())->method('render')->with($request, $exception);

        $exceptionHandler = new ExceptionHandler($app, $originalExceptionHandlerMock);
        $exceptionHandler->render($request, $exception);
    }

    /** @test */
    public function itRendersNonSymfonyConsoleExceptionsWithSymfony(): void
    {
        $app = $this->createApplication();
        $exception = new InvalidArgumentException();
        $output = new BufferedOutput();

        $originalExceptionHandlerMock = $this->createMock(ExceptionHandlerContract::class);
        $originalExceptionHandlerMock->expects($this->once())->method('renderForConsole')->with($output, $exception);

        $exceptionHandler = new ExceptionHandler($app, $originalExceptionHandlerMock);
        $exceptionHandler->renderForConsole($output, $exception);
    }

    /** @test */
    public function isInspectorGetsTrace(): void
    {
        $method = new ReflectionMethod(Inspector::class, 'getTrace');
        $method->setAccessible(true);

        $exception = new Exception('Foo');

        $this->assertSame($method->invokeArgs(new Inspector($exception), [$exception]), $exception->getTrace());
    }

    /** @test */
    public function itProvidesOnlyTheProviderContract(): void
    {
        $app = $this->createApplication();
        $provides = (new CollisionServiceProvider($app))->provides();
        $this->assertEquals([Provider::class], $provides);
    }

    /**
     * Creates a new instance of QuantaQuirk Application.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createApplication()
    {
        $app = $this->createPartialMock(Application::class, ['runningInConsole', 'runningUnitTests']);

        Container::setInstance($app);

        $app->singleton(
            ExceptionHandlerContract::class,
            function () use ($app) {
                return new \QuantaQuirk\Foundation\Exceptions\Handler($app);
            }
        );

        return $app;
    }
}
