<?php

namespace Tests\Feature\Extensions;

use App\Services\Extensions\Registries\HookRegistry;
use App\Services\Extensions\Registries\SlotRegistry;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Contract tests for the two extension-SDK registries every installed
 * extension builds on.
 *
 * The promise these make is isolation: one misbehaving extension must never
 * take down the core action that fired a hook, or the page rendering a slot.
 * Neither registry touches the database, so no RefreshDatabase here.
 */
class ExtensionRegistriesTest extends TestCase
{
    // ── Hooks ────────────────────────────────────────────────────

    public function test_listeners_run_in_priority_order_with_the_fired_arguments(): void
    {
        $hooks = new HookRegistry;
        $seen = [];

        $hooks->listen('demo.hook', function (string $who) use (&$seen) {
            $seen[] = "second:{$who}";
        }, 200);

        $hooks->listen('demo.hook', function (string $who) use (&$seen) {
            $seen[] = "first:{$who}";
        }, 50);

        $hooks->fire('demo.hook', 'alice');

        $this->assertSame(['first:alice', 'second:alice'], $seen);
    }

    public function test_a_throwing_listener_is_logged_and_the_others_still_run(): void
    {
        Log::spy();

        $hooks = new HookRegistry;
        $reached = false;

        $hooks->listen('demo.hook', fn () => throw new RuntimeException('boom'), 10);
        $hooks->listen('demo.hook', function () use (&$reached) {
            $reached = true;
        }, 20);

        // The core action that fires a hook must not see the exception.
        $hooks->fire('demo.hook');

        $this->assertTrue($reached, 'A failing listener must not stop later listeners.');
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_firing_a_hook_nobody_listens_to_is_a_no_op(): void
    {
        $hooks = new HookRegistry;

        $hooks->fire('nobody.listening', 'arg');

        $this->assertFalse($hooks->has('nobody.listening'));
        $this->assertSame([], $hooks->summary());
    }

    public function test_summary_counts_listeners_per_hook(): void
    {
        $hooks = new HookRegistry;

        $hooks->listen('a', fn () => null);
        $hooks->listen('a', fn () => null);
        $hooks->listen('b', fn () => null);

        $this->assertTrue($hooks->has('a'));
        $this->assertSame(['a' => 2, 'b' => 1], $hooks->summary());
    }

    // ── Slots ────────────────────────────────────────────────────

    public function test_compose_resolves_props_and_orders_by_priority(): void
    {
        $slots = new SlotRegistry;

        $slots->register('home.right', 'Second', fn () => ['n' => 2], null, 200);
        $slots->register('home.right', 'First', fn () => ['n' => 1], 'some.permission', 50);

        $composed = $slots->compose();

        $this->assertSame(['First', 'Second'], array_column($composed['home.right'], 'component'));
        $this->assertSame(['n' => 1], $composed['home.right'][0]['props']);
        $this->assertSame('some.permission', $composed['home.right'][0]['permission']);
    }

    public function test_data_closures_are_lazy_until_compose(): void
    {
        $slots = new SlotRegistry;
        $calls = 0;

        $slots->register('home.right', 'Widget', function () use (&$calls) {
            $calls++;

            return [];
        });

        $this->assertSame(0, $calls, 'Registering must not evaluate the data closure.');

        $slots->compose();

        $this->assertSame(1, $calls);
    }

    public function test_a_throwing_data_closure_skips_only_that_component(): void
    {
        $slots = new SlotRegistry;

        $slots->register('home.right', 'Broken', fn () => throw new RuntimeException('boom'), null, 10);
        $slots->register('home.right', 'Healthy', fn () => ['ok' => true], null, 20);

        $composed = $slots->compose();

        $this->assertSame(['Healthy'], array_column($composed['home.right'], 'component'));
    }

    public function test_a_null_data_closure_result_hides_the_component(): void
    {
        $slots = new SlotRegistry;

        // Returning null is the documented way to render conditionally.
        $slots->register('home.right', 'Hidden', fn () => null);
        $slots->register('home.right', 'Shown', fn () => ['ok' => true]);

        $this->assertSame(['Shown'], array_column($slots->compose()['home.right'], 'component'));
    }

    public function test_a_slot_without_a_data_closure_composes_with_empty_props(): void
    {
        $slots = new SlotRegistry;

        $slots->register('home.right', 'Static');

        $this->assertSame([], $slots->compose()['home.right'][0]['props']);
    }

    public function test_unregister_removes_every_entry_for_a_component(): void
    {
        $slots = new SlotRegistry;

        $slots->register('home.right', 'Gone', fn () => []);
        $slots->register('home.right', 'Kept', fn () => []);

        $slots->unregister('home.right', 'Gone');

        $this->assertTrue($slots->has('home.right'));
        $this->assertSame(['Kept'], array_column($slots->compose()['home.right'], 'component'));
    }

    public function test_unregistering_an_unknown_slot_is_harmless(): void
    {
        $slots = new SlotRegistry;

        $slots->unregister('never.registered', 'Whatever');

        $this->assertFalse($slots->has('never.registered'));
        $this->assertSame([], $slots->compose());
    }

    public function test_a_slot_left_empty_after_filtering_is_omitted_entirely(): void
    {
        $slots = new SlotRegistry;

        // Its only component opts out, so the slot should not reach the page
        // as an empty array for the frontend to special-case.
        $slots->register('home.right', 'Hidden', fn () => null);

        $this->assertTrue($slots->has('home.right'));
        $this->assertArrayNotHasKey('home.right', $slots->compose());
    }
}
