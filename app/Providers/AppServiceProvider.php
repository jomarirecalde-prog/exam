<?php

namespace App\Providers;

use App\Models\Examination;
use App\Policies\ExaminationPolicy;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Examination::class, ExaminationPolicy::class);

        $this->configureForVercel();

        date_default_timezone_set(config('examination.timezone', 'Asia/Manila'));

        if ($root = config('app.url')) {
            if (getenv('VERCEL') === '1') {
                $root = preg_replace('#^http://#i', 'https://', $root);
                URL::forceScheme('https');
            }

            URL::forceRootUrl($root);
        }

        $this->configureLivewireForSubdirectory();
    }

    /**
     * Livewire emits root-relative URLs such as /livewire/livewire.js.
     * When the app is served from a subdirectory (XAMPP: /examination/public),
     * the browser otherwise requests http://localhost/livewire/livewire.js.
     */
    protected function configureLivewireForSubdirectory(): void
    {
        $basePath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
        $publishedAssets = public_path('vendor/livewire/manifest.json');

        // Prefer published static assets (public/vendor/livewire). They work on
        // Vercel's static file routes and avoid subdirectory path issues.
        if ($basePath !== '' && ! is_file($publishedAssets)) {
            $livewireScript = config('app.debug') ? 'livewire.js' : 'livewire.min.js';
            config(['livewire.asset_url' => $basePath.'/livewire/'.$livewireScript]);
        }

        $this->app['events']->listen(RequestHandled::class, function (RequestHandled $event) use ($basePath) {
            $prefix = $event->request->getBasePath() ?: $basePath;

            if ($prefix === '' || $prefix === '/') {
                return;
            }

            $response = $event->response;
            $contentType = (string) $response->headers->get('content-type');

            if (! str_contains($contentType, 'text/html')) {
                return;
            }

            $html = $response->getContent();

            if (! is_string($html) || ! str_contains($html, '/livewire/')) {
                return;
            }

            $response->setContent(str_replace(
                [
                    'src="/livewire/',
                    'data-update-uri="/livewire/',
                ],
                [
                    'src="'.$prefix.'/livewire/',
                    'data-update-uri="'.$prefix.'/livewire/',
                ],
                $html
            ));
        });
    }

    protected function configureForVercel(): void
    {
        if (getenv('VERCEL') !== '1') {
            return;
        }

        $tmpStorage = '/tmp/storage';

        foreach (['framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
            $path = $tmpStorage.'/'.$dir;

            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        config([
            'logging.default' => 'stderr',
            'logging.channels.stack.channels' => ['stderr'],
            'filesystems.disks.local.root' => $tmpStorage,
            'session.lifetime' => max(1, (int) config('session.lifetime', 120)),
            'session.secure' => true,
            'session.same_site' => 'lax',
        ]);

        app()->useStoragePath($tmpStorage);

        $this->runPendingMigrations($tmpStorage);
    }

    protected function runPendingMigrations(string $tmpStorage): void
    {
        $stampFile = $tmpStorage.'/migrated.stamp';

        if (is_file($stampFile)) {
            return;
        }

        $lockFile = $tmpStorage.'/migrate.lock';
        $lock = fopen($lockFile, 'c+');

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return;
        }

        try {
            if (is_file($stampFile)) {
                return;
            }

            Artisan::call('migrate', ['--force' => true]);
            file_put_contents($stampFile, (string) time());
        } catch (Throwable $exception) {
            Log::error('Automatic Vercel migration failed.', [
                'error' => $exception->getMessage(),
            ]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
