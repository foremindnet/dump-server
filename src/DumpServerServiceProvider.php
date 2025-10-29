<?php

namespace Foremind\DumpServer;

use Foremind\DumpServer\Command\DumpServerCommand;
use Illuminate\Support\ServiceProvider;
use Override;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Server\Connection;
use Symfony\Component\VarDumper\VarDumper;

class DumpServerServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void {
        $this->mergeConfigFrom(__DIR__.'/../config/dump-server.php', 'dump-server');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/dump-server.php' => config_path('dump-server.php'),
        ]);

        $host = config('dump-server.host');

        $connection = new Connection($host, [
            'request' => new RequestContextProvider($this->app['request']),
            'source' => new SourceContextProvider('utf-8', base_path()),
        ]);

        VarDumper::setHandler(function ($var) use ($connection) {
            $this->app->makeWith(Dumper::class, ['connection' => $connection])->dump($var);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                DumpServerCommand::class,
            ]);
        }
    }
}
