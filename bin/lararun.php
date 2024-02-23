<?php


$dir = null;
$dirs = [getcwd(), __DIR__ . '/../../../..'];
foreach ($dirs as $d) {
    if (file_exists($d . '/vendor/autoload.php')) {
        $dir = $d;
        break;
    }
}

if (null === $dir) {
    die("Could not determine vendor/autoload.php location, quitting.");
}

require_once $dir . '/vendor/autoload.php';

$args = $argv;
$verbosity = 2;
foreach($argv as $i => $value) {
    if (preg_match('/^\d$/', $value)) {
        $verbosity = intval($value);
        unset($args[$i]);
        $args = array_values($args);
        break;
    }
}

$classes_dir = $dir . '/' . ($args[1] ?? 'app');
$data_dir = $dir . '/' . ($args[2] ?? 'tests/php-tt-data');

if (!is_dir($classes_dir)) {
    die("$classes_dir is not a directory, quitting.");
}

//boot laravel

$app = new class($dir) extends Illuminate\Foundation\Application
{
    public function registerConfiguredProviders()
    {
        $to_remove = ['Illuminate\Database\DatabaseServiceProvider'];
        $defaultProviders = $this->make('config')->get('app.providers');
        $defaultProviders = array_values(array_filter($defaultProviders, static function($item) use($to_remove) {
            return !in_array($item, $to_remove);
        }));

        $providers = Illuminate\Support\Collection::make($defaultProviders)
            ->partition(fn ($provider) => str_starts_with($provider, 'Illuminate\\'));

        $providers->splice(1, 0, [$this->make(Illuminate\Foundation\PackageManifest::class)->providers()]);

        (new Illuminate\Foundation\ProviderRepository($this, new Illuminate\Filesystem\Filesystem, $this->getCachedServicesPath()))
            ->load($providers->collapse()->toArray());
    }

    protected function registerBaseServiceProviders()
    {
        $this->register(new Illuminate\Events\EventServiceProvider($this));
        $this->register(new class($this) extends  Illuminate\Log\LogServiceProvider{
            public function register()
            {
                $this->app->singleton('log', function ($app) {
                    return new class($app) extends Illuminate\Log\LogManager{
                        public function emergency($message, array $context = []): void
                        {
                        }
                        public function alert($message, array $context = []): void
                        {
                        }
                        public function critical($message, array $context = []): void
                        {
                        }
                        public function error($message, array $context = []): void
                        {
                        }
                        public function warning($message, array $context = []): void
                        {
                        }
                        public function notice($message, array $context = []): void
                        {
                        }
                        public function info($message, array $context = []): void
                        {
                        }
                        public function debug($message, array $context = []): void
                        {
                        }
                        public function log($level, $message, array $context = []): void
                        {
                        }
                    };
                });
            }
        });
        $this->register(new Illuminate\Routing\RoutingServiceProvider($this));
    }
};


$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);



$Tt = new \Radziuk\PhpTT\Tt();
$Tt->setVerbosity($verbosity)
    ->showWarnings($verbosity > 1);

$data_dir = is_dir($data_dir) ? $data_dir : '';

$cache_dir = '';
// $cache_dir = getcwd() . '/storage/tt-cache';
$Tt->run($classes_dir, $data_dir, $cache_dir);