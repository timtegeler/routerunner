<?php

namespace TimTegeler\Routerunner;

use DI\ContainerBuilder;
use GuzzleHttp\Psr7\Response;
use Interop\Container\ContainerInterface;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TimTegeler\Routerunner\Components\Dispatcher;
use TimTegeler\Routerunner\Components\Execution;
use TimTegeler\Routerunner\Components\Parser;
use TimTegeler\Routerunner\Components\Request;
use TimTegeler\Routerunner\Components\Router;
use TimTegeler\Routerunner\Middleware\Middleware;
use TimTegeler\Routerunner\PostProcessor\PostProcessorInterface;

/**
 * Class Routerunner
 * @package TimTegeler\Routerunner
 */
class Routerunner implements MiddlewareInterface
{

    /**
     * @var Router
     */
    private $router;
    /**
     * @var Parser
     */
    private $parser;
    /**
     * @var Dispatcher
     */
    private $dispatcher;
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Routerunner constructor.
     * @param string $configFilePath
     * @param ContainerInterface $container
     * @throws Exception\ParseException
     */
    public function __construct($configFilePath, ContainerInterface $container = null)
    {
        if ($container == null) {
            $container = ContainerBuilder::buildDevContainer();
        }
        $this->container = $container;
        $this->parser = new Parser();
        $this->router = new Router();
        $this->dispatcher = new Dispatcher($container);
        $config = $this->parser->parse($configFilePath);
        $this->router->setFallback($config->getFallBack());
        $this->router->addRoutes($config->getRoutes());
        $this->router->setBasePath($config->getBasePath());
    }

    /**
     * @param Execution $execution
     * @return mixed
     */
    protected function dispatch(Execution $execution)
    {
        return $this->dispatcher->dispatch($execution);
    }

    /**
     * @param string $method
     * @param string $path
     * @return Execution
     */
    protected function route($method, $path)
    {
        return $this->router->route(new Request($method, $path));
    }

    /**
     * @param Middleware $middleware
     */
    public function registerMiddleware(Middleware $middleware)
    {
        $this->router->registerMiddleware($middleware);
    }

    /**
     * @param PostProcessorInterface $postProcessor
     */
    public function setPostProcessor(PostProcessorInterface $postProcessor)
    {
        $this->dispatcher->setPostProcessor($postProcessor);
    }

    /**
     * @param $enable
     */
    public function setCaching($enable)
    {
        $this->parser->setCaching($enable);
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware component to create the response.
     *
     * @param ServerRequestInterface $request
     * @param DelegateInterface $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $this->container->set(ServerRequestInterface::class, $request);
        $result = $this->dispatch($this->route($request->getMethod(), $request->getRequestTarget()));
        if ($result instanceof ResponseInterface) {
            return $result;
        } else {
            return $response = (new Response())
                ->withProtocolVersion('1.1')
                ->withBody(\GuzzleHttp\Psr7\stream_for($result));
        }
    }
}