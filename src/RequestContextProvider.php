<?php

namespace Foremind\DumpServer;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\ContextProvider\ContextProviderInterface;

class RequestContextProvider implements ContextProviderInterface
{
    private ?Request $currentRequest;

    private VarCloner $cloner;

    public function __construct(?Request $currentRequest = null)
    {
        $this->currentRequest = $currentRequest;
        $this->cloner = new VarCloner;
        $this->cloner->setMaxItems(0);
    }

    /** @return array<string, mixed>|null */
    public function getContext(): ?array
    {
        if ($this->currentRequest === null) {
            return null;
        }

        $controller = null;

        /** @var Route|null $route */
        if ($route = $this->currentRequest->route()) {
            $controller = $route->controller;

            if (! $controller && ! is_string($route->action['uses'])) {
                $controller = $route->action['uses'];
            }
        }

        return [
            'uri' => $this->currentRequest->getUri(),
            'method' => $this->currentRequest->getMethod(),
            'controller' => $controller ? $this->cloner->cloneVar(class_basename($controller)) : $this->cloner->cloneVar(null),
            'identifier' => spl_object_hash($this->currentRequest),
        ];
    }
}
