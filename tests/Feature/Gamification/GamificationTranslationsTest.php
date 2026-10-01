<?php

namespace Tests\Feature\Gamification;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GamificationTranslationsTest extends TestCase
{
    #[Test]
    public function it_ships_the_same_keys_in_french_and_english(): void
    {
        $directory = base_path('functional/gamification/lang');

        foreach (glob("{$directory}/fr/*.php") ?: [] as $frenchFile) {
            $englishFile = "{$directory}/en/".basename($frenchFile);

            $this->assertFileExists($englishFile);
            $this->assertSame(
                array_keys(Arr::dot(require $frenchFile)),
                array_keys(Arr::dot(require $englishFile)),
                basename($frenchFile),
            );
        }
    }

    #[Test]
    public function it_resolves_the_layer_namespace(): void
    {
        $this->app->setLocale('fr');

        $this->assertSame('Joueur', __('gamification::player.title'));
        $this->assertSame('Record : 12', __('gamification::player.streak_best', ['count' => 12]));
    }

    #[Test]
    public function it_keeps_the_badge_dashboard_line_in_the_dashboard_file(): void
    {
        $this->app->setLocale('en');

        $this->assertArrayNotHasKey('dashboard', require base_path('functional/gamification/lang/en/badges.php'));
        $this->assertArrayNotHasKey('dashboard', require base_path('functional/gamification/lang/fr/badges.php'));
        $this->assertSame('Badges: 3 / 30', __('gamification::dashboard.badges', ['earned' => 3, 'total' => 30]));
    }
}
