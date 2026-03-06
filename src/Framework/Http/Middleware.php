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
     * Create a middleware layer, resolving through container for DI support.
     *
     * @throws \InvalidArgumentException if the layer does not implement MiddlewareInterface
     */
    private function createLayer($nextLayer, $layer): Closure
    {
        // Resolve class name strings through the DI container
        if (is_string($layer) && class_exists($layer)) {
            $layer = container()->get($layer);
        }

        // Validate that the resolved layer implements the interface
        if (!($layer instanceof MiddlewareInterface)) {
            $type = is_object($layer) ? get_class($layer) : (is_string($layer) ? $layer : gettype($layer));
            throw new \InvalidArgumentException(
                "Middleware layer must implement MiddlewareInterface, got: {$type}"
            );
        }

        return fn ($object) => $layer->handle($object, $nextLayer);
    }
}
