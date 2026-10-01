<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Enums\ChallengeUnit;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Exceptions\InvalidChallengeConfigException;
use Functional\Gamification\Exceptions\MissingChallengeTemplateConfigException;
use Functional\Goals\Enums\GoalMetric;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChallengeTemplateKeyTest extends TestCase
{
    /**
     * @return array<string, array{ChallengeTemplateKey, GoalMetric, GamificationDomain, ChallengeUnit}>
     */
    public static function templates(): array
    {
        return [
            'sport distance' => [ChallengeTemplateKey::SportDistance, GoalMetric::SportDistance, GamificationDomain::Sport, ChallengeUnit::Kilometers],
            'sport elevation' => [ChallengeTemplateKey::SportElevation, GoalMetric::SportElevation, GamificationDomain::Sport, ChallengeUnit::Meters],
            'sport activity count' => [ChallengeTemplateKey::SportActivityCount, GoalMetric::SportActivityCount, GamificationDomain::Sport, ChallengeUnit::Count],
            'sport moving time' => [ChallengeTemplateKey::SportMovingTime, GoalMetric::SportMovingTime, GamificationDomain::Sport, ChallengeUnit::Hours],
            'moto distance' => [ChallengeTemplateKey::MotoDistance, GoalMetric::MotoDistance, GamificationDomain::Moto, ChallengeUnit::Kilometers],
            'moto ride count' => [ChallengeTemplateKey::MotoRideCount, GoalMetric::MotoRideCount, GamificationDomain::Moto, ChallengeUnit::Count],
            'exploration cells' => [ChallengeTemplateKey::ExplorationCells, GoalMetric::ExplorationCells, GamificationDomain::Exploration, ChallengeUnit::Count],
        ];
    }

    #[Test]
    public function it_declares_the_seven_templates_in_their_stable_order(): void
    {
        $this->assertSame(
            ['sport_distance', 'sport_elevation', 'sport_activity_count', 'sport_moving_time', 'moto_distance', 'moto_ride_count', 'exploration_cells'],
            array_map(fn (ChallengeTemplateKey $template): string => $template->value, ChallengeTemplateKey::cases()),
        );
    }

    #[Test]
    public function it_binds_every_template_to_a_period_bound_metric(): void
    {
        foreach (ChallengeTemplateKey::cases() as $template) {
            $this->assertTrue($template->metric()->isPeriodBound(), $template->value);
        }
    }

    #[Test]
    #[DataProvider('templates')]
    public function it_maps_a_template_to_its_metric_domain_and_unit(
        ChallengeTemplateKey $template,
        GoalMetric $metric,
        GamificationDomain $domain,
        ChallengeUnit $unit,
    ): void {
        $this->assertSame($metric, $template->metric());
        $this->assertSame($domain, $template->domain());
        $this->assertSame($unit, $template->unit());
    }

    #[Test]
    public function it_lists_the_templates_of_a_domain_in_declaration_order(): void
    {
        $this->assertSame(
            [ChallengeTemplateKey::SportDistance, ChallengeTemplateKey::SportElevation, ChallengeTemplateKey::SportActivityCount, ChallengeTemplateKey::SportMovingTime],
            ChallengeTemplateKey::forDomain(GamificationDomain::Sport),
        );
        $this->assertSame(
            [ChallengeTemplateKey::MotoDistance, ChallengeTemplateKey::MotoRideCount],
            ChallengeTemplateKey::forDomain(GamificationDomain::Moto),
        );
        $this->assertSame([ChallengeTemplateKey::ExplorationCells], ChallengeTemplateKey::forDomain(GamificationDomain::Exploration));
    }

    #[Test]
    public function it_lists_no_template_for_a_domain_without_challenges(): void
    {
        $this->assertSame([], ChallengeTemplateKey::forDomain(GamificationDomain::Health));
        $this->assertSame([], ChallengeTemplateKey::forDomain(GamificationDomain::Finance));
        $this->assertSame([], ChallengeTemplateKey::forDomain(GamificationDomain::Todo));
    }

    #[Test]
    public function it_picks_the_eligible_template_indexed_by_the_iso_week_modulo_their_count(): void
    {
        $eligible = [ChallengeTemplateKey::SportDistance, ChallengeTemplateKey::SportActivityCount];

        $this->assertSame(ChallengeTemplateKey::SportDistance, ChallengeTemplateKey::pickFor($eligible, 40));
        $this->assertSame(ChallengeTemplateKey::SportActivityCount, ChallengeTemplateKey::pickFor($eligible, 41));
        $this->assertSame(ChallengeTemplateKey::SportActivityCount, ChallengeTemplateKey::pickFor($eligible, 53));
    }

    #[Test]
    public function it_rotates_through_every_template_of_a_domain(): void
    {
        $this->assertSame(
            ChallengeTemplateKey::SportMovingTime,
            ChallengeTemplateKey::pickFor(ChallengeTemplateKey::forDomain(GamificationDomain::Sport), 43),
        );
    }

    #[Test]
    public function it_picks_nothing_when_no_template_is_eligible(): void
    {
        $this->assertNull(ChallengeTemplateKey::pickFor([], 40));
    }

    #[Test]
    public function it_reads_the_settings_of_a_template_from_the_configuration(): void
    {
        $settings = ChallengeTemplateKey::SportDistance->settings();

        $this->assertSame(1.0, $settings->step);
        $this->assertSame(5.0, $settings->floor);
        $this->assertSame(300.0, $settings->cap);
    }

    #[Test]
    public function it_configures_consistent_settings_for_every_template(): void
    {
        foreach (ChallengeTemplateKey::cases() as $template) {
            $settings = $template->settings();

            $this->assertGreaterThan(0.0, $settings->step, $template->value);
            $this->assertGreaterThan(0.0, $settings->floor, $template->value);
            $this->assertGreaterThanOrEqual($settings->floor, $settings->cap, $template->value);
        }
    }

    #[Test]
    public function it_builds_the_config_path_of_a_template(): void
    {
        $this->assertSame('gamification.challenges.templates.sport_distance', ChallengeTemplateKey::SportDistance->configPath());
    }

    #[Test]
    public function it_names_the_template_whose_configuration_is_missing(): void
    {
        config(['gamification.challenges.templates.sport_distance' => null]);

        $this->expectExceptionObject(new MissingChallengeTemplateConfigException(ChallengeTemplateKey::SportDistance));

        ChallengeTemplateKey::SportDistance->settings();
    }

    #[Test]
    public function it_refuses_a_zero_step_and_names_its_config_path(): void
    {
        config(['gamification.challenges.templates.sport_distance.step' => 0]);

        $this->expectExceptionObject(InvalidChallengeConfigException::notPositive('gamification.challenges.templates.sport_distance.step', 0));

        ChallengeTemplateKey::SportDistance->settings();
    }

    #[Test]
    public function it_refuses_a_floor_that_is_not_positive(): void
    {
        config(['gamification.challenges.templates.sport_elevation.floor' => -100]);

        $this->expectExceptionObject(InvalidChallengeConfigException::notPositive('gamification.challenges.templates.sport_elevation.floor', -100));

        ChallengeTemplateKey::SportElevation->settings();
    }

    #[Test]
    public function it_refuses_a_cap_below_the_floor(): void
    {
        config(['gamification.challenges.templates.sport_distance.cap' => 2]);

        $this->expectExceptionObject(InvalidChallengeConfigException::belowFloor('gamification.challenges.templates.sport_distance.cap', 2));

        ChallengeTemplateKey::SportDistance->settings();
    }

    #[Test]
    public function it_refuses_a_setting_that_is_not_numeric(): void
    {
        config(['gamification.challenges.templates.moto_distance.floor' => 'fifty']);

        $this->expectExceptionObject(InvalidChallengeConfigException::notPositive('gamification.challenges.templates.moto_distance.floor', 'fifty'));

        ChallengeTemplateKey::MotoDistance->settings();
    }

    #[Test]
    public function it_refuses_a_setting_that_is_absent(): void
    {
        config(['gamification.challenges.templates.moto_ride_count' => ['step' => 1, 'floor' => 1]]);

        $this->expectExceptionObject(InvalidChallengeConfigException::belowFloor('gamification.challenges.templates.moto_ride_count.cap', null));

        ChallengeTemplateKey::MotoRideCount->settings();
    }

    #[Test]
    public function it_labels_a_template_in_both_locales(): void
    {
        $this->app->setLocale('fr');
        $this->assertSame('Distance sportive', ChallengeTemplateKey::SportDistance->label());

        $this->app->setLocale('en');
        $this->assertSame('Sport distance', ChallengeTemplateKey::SportDistance->label());
    }

    #[Test]
    public function it_describes_a_distance_template_with_the_target_in_its_unit_in_french(): void
    {
        $this->app->setLocale('fr');

        $this->assertSame('Parcourez 28 km cette semaine.', ChallengeTemplateKey::SportDistance->description(28.0));
    }

    #[Test]
    public function it_describes_a_distance_template_with_the_target_in_its_unit_in_english(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('Cover 28 km this week.', ChallengeTemplateKey::SportDistance->description(28.0));
    }

    #[Test]
    public function it_agrees_the_french_description_of_a_count_template_with_its_target(): void
    {
        $this->app->setLocale('fr');

        $this->assertSame('Enregistrez 1 activité sportive cette semaine.', ChallengeTemplateKey::SportActivityCount->description(1.0));
        $this->assertSame('Enregistrez 2 activités sportives cette semaine.', ChallengeTemplateKey::SportActivityCount->description(2.0));
    }

    #[Test]
    public function it_agrees_the_english_description_of_a_count_template_with_its_target(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('Log 1 workout this week.', ChallengeTemplateKey::SportActivityCount->description(1.0));
        $this->assertSame('Log 3 workouts this week.', ChallengeTemplateKey::SportActivityCount->description(3.0));
    }

    #[Test]
    public function it_translates_every_template_name_and_description_in_both_locales(): void
    {
        foreach (['fr', 'en'] as $locale) {
            $this->app->setLocale($locale);

            foreach (ChallengeTemplateKey::cases() as $template) {
                foreach ([1.0, 12.0] as $target) {
                    $description = $template->description($target);

                    $this->assertStringNotContainsString('gamification::', $template->label(), "{$locale}.{$template->value}.name");
                    $this->assertStringNotContainsString('gamification::', $description, "{$locale}.{$template->value}.description");
                    $this->assertStringNotContainsString(':target', $description, "{$locale}.{$template->value}.placeholder");
                    $this->assertStringContainsString('1', $description, "{$locale}.{$template->value}.target");
                }
            }
        }
    }
}
