<?php
namespace Pion\Laravel\ChunkUpload\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Pion\Laravel\ChunkUpload\Commands\ClearChunksCommand;
use Pion\Laravel\ChunkUpload\Config\AbstractConfig;
use Pion\Laravel\ChunkUpload\Config\FileConfig;
use Pion\Laravel\ChunkUpload\Storage\ChunkStorage;
use Storage;

class ChunkUploadServiceProvider extends ServiceProvider
{

    /**
     * When the service is being booted
     */
    public function boot()
    {
        // Get the schedule config
        $scheduleConfig = AbstractConfig::config()->scheduleConfig();

        // Run only if schedule is enabled
        if (Arr::get($scheduleConfig, "enabled", false) === true) {

            // Wait until the app is fully booted
            $this->app->booted(function () use ($scheduleConfig) {

                // Get the scheduler instance
                /** @var Schedule $schedule */
                $schedule = $this->app->make(Schedule::class);

                // Register the clear chunks with custom schedule
                $schedule->command('uploads:clear')->cron(Arr::get($scheduleConfig, "cron", "* * * * *"));
            });
        }
    }


    /**
     * Register the package requirements.
     *
     * @see ChunkUploadServiceProvider::registerConfig()
     */
    public function register()
    {
        // Register the commands
        $this->commands([
            ClearChunksCommand::class
        ]);

        // Register the config
        $this->registerConfig();

        // Register the config via abstract instance
        $this->app->singleton(AbstractConfig::class, function () {
            return new FileConfig();
        });

        // Register the config via abstract instance
        $this->app->singleton(ChunkStorage::class, function (Application $app) {
            /** @var AbstractConfig $config */
            $config = $app->make(AbstractConfig::class);

            // Build the chunk storage
            return new ChunkStorage(Storage::disk($config->chunksDiskName()), $config);
        });
    }

    /**
     * Publishes and mergers the config. Uses the FileConfig
     *
     * @see FileConfig
     * @see ServiceProvider::publishes
     * @see ServiceProvider::mergeConfigFrom
     */
    protected function registerConfig()
    {
        // Config options
        $configIndex = FileConfig::FILE_NAME;
        $configFileName = FileConfig::FILE_NAME.".php";
        $configPath = __DIR__.'/../../config/'.$configFileName;

        // Publish the config
        $this->publishes([
            $configPath => config_path($configFileName),
        ]);

        // Merge the default config to prevent any crash or unfilled configs
        $this->mergeConfigFrom(
            $configPath, $configIndex
        );
    }

}