<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;
use ScBudgetWeb\SiteBuilder\Pages\Slot;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Pages render in a stand-in public layout (tests/Fixtures/views), with a home route and a "home" slot, as in an app.
 * Permissions are plain gates: admin@example.com has them all.
 */
abstract class PageTestCase extends TestCase
{
    public const PERMISSIONS = ['view pages', 'create pages', 'update pages', 'delete pages'];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // A block that fails to render throws instead of being skipped quietly.
        $app['config']->set('app.debug', true);

        // The theme's layout needs SaaSykit; the stand-in layout has the same slots.
        $app['config']->set('site-builder.theme.enabled', false);
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views', ...$app['config']->get('view.paths', [])]);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/', fn () => 'The built-in home page')->name('home');
        $router->get('/blog', fn () => 'Blog')->name('blog');
        $router->get('/login', fn () => 'Log in')->name('login');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(SiteBuilder::class)->slots([
            Slot::make('home', 'Home page', fn (): string => route('home')),
            Slot::make('contact', 'Contact', fn (): string => url('/contact'))->requires('call_to_action'),
        ]);

        foreach (self::PERMISSIONS as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->getAttribute('email') === 'admin@example.com');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function page(array $attributes = []): Page
    {
        return Page::query()->create([
            'title' => 'About us',
            'slug' => 'about-us',
            'status' => PageStatus::Published,
            'blocks' => [],
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, data: array<string, mixed>}
     */
    protected static function block(string $type, array $data = []): array
    {
        return ['type' => $type, 'data' => $data];
    }

    protected function user(string $email = 'admin@example.com'): User
    {
        $user = new User;
        $user->forceFill(['id' => crc32($email), 'name' => 'Test', 'email' => $email]);

        return $user;
    }
}
