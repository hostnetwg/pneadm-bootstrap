<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class DashboardOrdersStatsApiTest extends TestCase
{
    private array $createdUserIds = [];

    private array $createdRoleIds = [];

    protected function tearDown(): void
    {
        if ($this->createdUserIds !== []) {
            User::query()
                ->withTrashed()
                ->whereIn('id', $this->createdUserIds)
                ->forceDelete();
        }

        if ($this->createdRoleIds !== []) {
            Role::query()
                ->whereIn('id', $this->createdRoleIds)
                ->delete();
        }

        parent::tearDown();
    }

    public function test_guest_cannot_access_orders_stats_api(): void
    {
        $this->getJson(route('api.dashboard.orders-stats'))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_orders_stats_snapshot(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('users') || ! \Illuminate\Support\Facades\Schema::hasTable('roles')) {
            $this->markTestSkipped('Brak tabel users/roles w testowej bazie adm.');
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('form_orders')
            || ! \Illuminate\Support\Facades\Schema::hasColumn('form_orders', 'cancelled_at')) {
            $this->markTestSkipped('Brak aktualnej tabeli form_orders w testowej bazie adm.');
        }

        $user = $this->userWithRole('manager');

        $this->actingAs($user)
            ->getJson(route('api.dashboard.orders-stats'))
            ->assertOk()
            ->assertJsonStructure([
                'as_of',
                'form_today',
                'form_yesterday',
                'form_handling',
                'deferred_handling',
                'online_handling',
                'latest_form_order_id',
            ]);
    }

    public function test_authenticated_user_can_fetch_orders_stats_with_sections(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('users') || ! \Illuminate\Support\Facades\Schema::hasTable('roles')) {
            $this->markTestSkipped('Brak tabel users/roles w testowej bazie adm.');
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('form_orders')
            || ! \Illuminate\Support\Facades\Schema::hasColumn('form_orders', 'cancelled_at')) {
            $this->markTestSkipped('Brak aktualnej tabeli form_orders w testowej bazie adm.');
        }

        $user = $this->userWithRole('manager');

        $this->actingAs($user)
            ->getJson(route('api.dashboard.orders-stats', ['sections' => 1]))
            ->assertOk()
            ->assertJsonStructure([
                'form_today',
                'latest_form_order_id',
                'sections' => [
                    'period' => ['total', 'online', 'deferred', 'avg', 'avg_label'],
                    'chart' => ['labels', 'labels_short', 'online', 'deferred', 'total'],
                    'recent_orders',
                    'course_schedule',
                    'chart_granularity',
                    'date_range' => ['from', 'to'],
                    'shortcuts' => ['form_handling'],
                ],
            ]);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()->where('name', $roleName)->first();
        if (! $role) {
            $role = Role::query()->create([
                'name' => $roleName,
                'display_name' => ucfirst(str_replace('_', ' ', $roleName)),
                'is_system' => true,
                'level' => $roleName === 'admin' ? 3 : 2,
            ]);
            $this->createdRoleIds[] = $role->id;
        }

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $this->createdUserIds[] = $user->id;

        return $user;
    }
}
