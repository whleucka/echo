<?php

namespace Echo\Framework\Http;

use Closure;

class Middleware
{
    public function __construct(private array $layers = [])
    {
    }

    public function layer($layers): Middleware
    {
        if ($layers instanceof Middleware) {
            $layers = $layers->toArray();
        }

        if ($layers instanceof MiddlewareInterface) {
            $layers = [$layers];
        }

        if (!is_array($layers)) {
            throw new \InvalidArgumentException(
                get_class($layers) . " is not compatible middleware"
            );
        }

        return new static(array_merge($this->layers, $layers));
    }

    public function handle(RequestInterface $request, Closure $core): ResponseInterface
    {
        $coreFunction = $this->createCoreFunction($core);

        $layers = array_reverse($this->layers);

        $next = array_reduce(
            $layers,
            function ($nextLayer, $layer) {
                return $this->createLayer($nextLayer, $layer);
            },
            $coreFunction
        );

        return $next($request);
    }

    public function toArray(): array
    {
        return $this->layers;
    }

    private function createCoreFunction(Closure $core): Closure
    {
        return fn ($object) => $core($object);
    }

    /**
     * Create a middleware layer, resolving through container for DI support
     */
    private function createLayer($nextLayer, $layer): Closure
    {
        // If it's a class name string, resolve through container
        if (is_string($layer) && class_exists($layer)) {
            $layer = container()->get($layer);
        } elseif (!($layer instanceof MiddlewareInterface)) {
            // If it's not already an instance, create it directly
            $layer = new $layer;
        }

        return fn ($object) => $layer->handle($object, $nextLayer);
    }
}
