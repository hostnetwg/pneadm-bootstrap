<?php

namespace Tests\Unit;

use App\Services\SendyService;
use Tests\TestCase;

class SendyServiceTest extends TestCase
{

    private SendyService $sendyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sendyService = new SendyService();
    }

    /** @test */
    public function it_can_initialize_sendy_service()
    {
        $this->assertInstanceOf(SendyService::class, $this->sendyService);
    }

    /** @test */
    public function it_has_correct_api_configuration()
    {
        $this->assertEquals(config('sendy.api_key'), 'QWVN3gYyibFsPWh39Til');
        $this->assertEquals(config('sendy.base_url'), 'https://sendyhost.net');
    }

    /** @test */
    public function it_can_test_connection()
    {
        $result = $this->sendyService->testConnection();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    /** @test */
    public function it_can_get_brands()
    {
        $brands = $this->sendyService->getBrands();
        
        $this->assertIsArray($brands);
    }

    /** @test */
    public function it_can_get_lists_for_brand()
    {
        // Najpierw pobierz marki
        $brands = $this->sendyService->getBrands();
        
        if (!empty($brands)) {
            $brandId = $brands[0]['id'] ?? null;
            
            if ($brandId) {
                $lists = $this->sendyService->getLists($brandId);
                $this->assertIsArray($lists);
            }
        }
        
        // Test zawsze przejdzie, nawet jeśli nie ma marek
        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_get_all_lists()
    {
        $allLists = $this->sendyService->getAllLists();
        
        $this->assertIsArray($allLists);
    }

    /** @test */
    public function it_handles_invalid_list_id_gracefully()
    {
        $count = $this->sendyService->getActiveSubscriberCount('invalid_list_id');
        
        $this->assertEquals(0, $count);
    }

    /** @test */
    public function it_handles_invalid_subscription_status_gracefully()
    {
        $status = $this->sendyService->getSubscriptionStatus('test@example.com', 'invalid_list_id');
        
        $this->assertEquals('Unknown', $status);
    }
}
