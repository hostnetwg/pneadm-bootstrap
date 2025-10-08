<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestPubligoWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publigo:test-webhook 
                            {--course-id= : ID kursu do testowania}
                            {--email=test@example.com : Email uczestnika}
                            {--first-name=Jan : Imię uczestnika}
                            {--last-name=Kowalski : Nazwisko uczestnika}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test webhooka Publigo.pl';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $courseId = $this->option('course-id');
        $email = $this->option('email');
        $firstName = $this->option('first-name');
        $lastName = $this->option('last-name');

        if (!$courseId) {
            $this->error('Musisz podać ID kursu używając --course-id');
            return 1;
        }

        $webhookUrl = config('services.publigo.webhook_url', 'http://localhost/api/publigo/webhook');

        $payload = [
            'id' => time(), // order_id
            'user_id' => 123,
            'status' => 'Zakończone',
            'currency' => 'PLN',
            'date_completed' => now()->format('Y-m-d H:i:s'),
            'total' => 199.99,
            'payment_method' => 'automatic',
            'url_params' => [
                [
                    'product_id' => $courseId,
                    'details' => 'Test course details',
                    'external_id' => $courseId
                ]
            ],
            'items' => [
                [
                    'name' => 'Test Course',
                    'id' => $courseId,
                    'price_id' => 1,
                    'quantity' => 1,
                    'discount' => 0,
                    'subtotal' => 199.99,
                    'price' => 199.99
                ]
            ],
            'customer' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email
            ],
            'biling_address' => [
                'company_name' => 'Test Company',
                'tax_id' => '1234567890',
                'street' => 'Test Street',
                'building_number' => '1',
                'apartment_number' => '1',
                'postal' => '00-000',
                'city' => 'Warszawa',
                'country_code' => 'PL'
            ],
            'delivery' => [
                'type' => 'custom',
                'phone' => '123456789',
                'street' => 'Test Street',
                'building_number' => '1',
                'apartment_number' => '1',
                'postal_code' => '00-000',
                'city' => 'Warszawa',
                'parcel_locker_point' => null
            ],
            'additional_fields' => [
                'buy_as_gift' => false,
                'voucher_codes' => null,
                'phone_no' => '123456789',
                'additional_checkbox_checked' => false,
                'additional_checkbox_description' => 'Test checkbox',
                'additional_checkbox2_checked' => false,
                'additional_checkbox2_description' => 'Test checkbox 2',
                'order_comment' => 'Test order comment'
            ]
        ];

        $this->info('Wysyłam testowy webhook do: ' . $webhookUrl);
        $this->line('Payload: ' . json_encode($payload, JSON_PRETTY_PRINT));

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Publigo-Webhook-Test/1.0'
            ])->post($webhookUrl, $payload);

            $this->info('Status: ' . $response->status());
            $this->line('Response: ' . $response->body());

            if ($response->successful()) {
                $this->info('✅ Webhook test zakończony sukcesem!');
            } else {
                $this->error('❌ Webhook test zakończony błędem!');
            }

        } catch (\Exception $e) {
            $this->error('Błąd podczas wysyłania webhooka: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
