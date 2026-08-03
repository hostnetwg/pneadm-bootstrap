<?php

namespace Tests\Feature;

use App\Models\DebtCase;
use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionsPaginationFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_collections_pagination_links_keep_non_empty_filters(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        for ($i = 0; $i < 26; $i++) {
            $order = FormOrder::create([
                'product_name' => 'Szkolenie paginacja '.$i,
                'product_price' => 100,
                'order_date' => now()->subDays(5),
                'invoice_number' => 'PAG/'.$i.'/2026',
                'buyer_name' => 'Klient Testowy',
            ]);

            DebtCase::create([
                'form_order_id' => $order->id,
                'status' => DebtCase::STATUS_PROMISED,
                'customer_segment' => DebtCase::SEGMENT_VIP,
                'invoice_number' => 'PAG/'.$i.'/2026',
                'opened_at' => now(),
            ]);
        }

        $page1 = $this->actingAs($user)->get('/accounting/collections?search=Klient&status=promised&segment=vip');
        $page1->assertOk();

        $html = $page1->getContent();
        $this->assertMatchesRegularExpression('/href="[^"]*status=promised[^"]*page=2/', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*segment=vip[^"]*page=2/', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*search=Klient[^"]*page=2/', $html);
    }

    public function test_collections_pagination_keeps_all_status_filter_on_page_two(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        for ($i = 0; $i < 26; $i++) {
            $order = FormOrder::create([
                'product_name' => 'Szkolenie all '.$i,
                'product_price' => 100,
                'order_date' => now()->subDays(5),
                'invoice_number' => 'ALL/'.$i.'/2026',
            ]);

            DebtCase::create([
                'form_order_id' => $order->id,
                'status' => DebtCase::STATUS_CLOSED,
                'invoice_number' => 'ALL/'.$i.'/2026',
                'opened_at' => now()->subDays(3),
                'closed_at' => now()->subDay(),
            ]);
        }

        $page1 = $this->actingAs($user)->get('/accounting/collections?search=&status=all&segment=');
        $page1->assertOk();
        $page1->assertSee('ALL/25/2026');

        $html = $page1->getContent();
        $this->assertTrue(
            (bool) preg_match('/href="([^"]*page=2[^"]*)"/', $html, $m),
            'Expected a page=2 pagination link'
        );

        $page2Url = html_entity_decode($m[1]);
        $this->assertStringContainsString('status=all', $page2Url);

        $page2 = $this->actingAs($user)->get($page2Url);
        $page2->assertOk();
        $page2->assertSee('ALL/', false);
        $page2->assertDontSee('Brak spraw windykacyjnych dla wybranych filtrów');
        $page2->assertSee('value="all"', false);
    }

    public function test_legacy_empty_status_query_still_means_all_cases(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => 1,
        ]);

        $order = FormOrder::create([
            'product_name' => 'Zamknięte legacy',
            'product_price' => 100,
            'order_date' => now()->subDays(5),
            'invoice_number' => 'LEG/1/2026',
        ]);
        DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_CLOSED,
            'invoice_number' => 'LEG/1/2026',
            'opened_at' => now()->subDays(3),
            'closed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get('/accounting/collections?status=');
        $response->assertOk();
        $response->assertSee('LEG/1/2026');
        $response->assertSee('value="all"', false);
    }
}
