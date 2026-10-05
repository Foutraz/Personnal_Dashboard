<?php

namespace Tests\Feature\Lomkit;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Http\Controllers\Controller as RestController;
use PHPUnit\Framework\Attributes\Test;
use Technical\Osdd\Rest\Throttle\RestRateLimit;
use Tests\TestCase;

class RestRateLimitTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_defaults_to_one_hundred_and_twenty_requests_per_minute(): void
    {
        $limit = app(RestRateLimit::class)->limitFor(Request::create('/api/tasks'));

        $this->assertSame(120, $limit->maxAttempts);
    }

    #[Test]
    public function it_registers_the_named_limiter(): void
    {
        $this->assertNotNull(RateLimiter::limiter(RestRateLimit::NAME));
    }

    #[Test]
    public function it_throttles_a_user_above_the_limit(): void
    {
        config(['osdd.rest.requests_per_minute' => 2]);
        $user = User::factory()->create();

        $this->actingAs($user, 'api')->getJson('/api/tasks')->assertOk();
        $this->actingAs($user, 'api')->getJson('/api/tasks')->assertOk();
        $this->actingAs($user, 'api')->getJson('/api/tasks')->assertTooManyRequests();
    }

    #[Test]
    public function it_keeps_a_separate_bucket_for_each_user(): void
    {
        config(['osdd.rest.requests_per_minute' => 1]);
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user, 'api')->getJson('/api/tasks')->assertOk();
        $this->actingAs($user, 'api')->getJson('/api/tasks')->assertTooManyRequests();
        $this->actingAs($other, 'api')->getJson('/api/tasks')->assertOk();
    }

    #[Test]
    public function it_falls_back_to_the_ip_address_without_an_authenticated_user(): void
    {
        $request = Request::create('/api/tasks', 'GET', server: ['REMOTE_ADDR' => '203.0.113.9']);

        $limit = app(RestRateLimit::class)->limitFor($request);

        $this->assertStringContainsString('203.0.113.9', $limit->key);
    }

    #[Test]
    public function it_keys_an_authenticated_request_by_the_user_id(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/api/tasks', 'GET', server: ['REMOTE_ADDR' => '203.0.113.9']);
        $request->setUserResolver(fn (): User => $user);

        $limit = app(RestRateLimit::class)->limitFor($request);

        $this->assertStringContainsString($user->id, $limit->key);
        $this->assertStringNotContainsString('203.0.113.9', $limit->key);
    }

    #[Test]
    public function it_throttles_every_route_of_every_rest_controller(): void
    {
        $restRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'api/'))
            ->filter(fn (LaravelRoute $route): bool => is_subclass_of((string) $route->getControllerClass(), RestController::class));

        $unthrottledUris = $restRoutes
            ->reject(fn (LaravelRoute $route): bool => in_array(RestRateLimit::middleware(), $route->gatherMiddleware(), true))
            ->map(fn (LaravelRoute $route): string => $route->uri())
            ->values()
            ->all();

        $this->assertGreaterThan(50, $restRoutes->count());
        $this->assertSame([], $unthrottledUris);
    }
}
