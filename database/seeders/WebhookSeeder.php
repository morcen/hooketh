<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\Delivery;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WebhookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the test user
        $user = User::where('email', 'test@example.com')->first();
        
        if (!$user) {
            $this->command->error('Test user not found. Please run the main seeder first.');
            return;
        }
        
        $this->command->info('Creating webhook endpoints...');
        
        // Create various endpoints for different services
        $endpoints = [
            [
                'name' => 'Payment Processing Service',
                'url' => 'https://api.paymentservice.com/webhooks/payment',
                'description' => 'Handles payment-related webhooks including successful payments, failures, and refunds',
                'secret_key' => 'payment_secret_' . bin2hex(random_bytes(16)),
                'is_active' => true,
            ],
            [
                'name' => 'User Management System',
                'url' => 'https://api.usermgmt.com/webhook/user-events',
                'description' => 'Receives user lifecycle events such as registration, profile updates, and account deletions',
                'secret_key' => 'user_secret_' . bin2hex(random_bytes(16)),
                'is_active' => true,
            ],
            [
                'name' => 'Order Fulfillment API',
                'url' => 'https://fulfillment.example.com/webhooks/orders',
                'description' => 'Processes order status updates and fulfillment notifications',
                'secret_key' => 'order_secret_' . bin2hex(random_bytes(16)),
                'is_active' => true,
            ],
            [
                'name' => 'Email Marketing Platform',
                'url' => 'https://marketing.example.com/api/webhooks',
                'description' => 'Receives customer behavior events for marketing automation',
                'secret_key' => 'marketing_secret_' . bin2hex(random_bytes(16)),
                'is_active' => false, // Temporarily disabled
            ],
            [
                'name' => 'Analytics Dashboard',
                'url' => 'https://analytics.internal.com/webhook/events',
                'description' => 'Collects business events for real-time analytics and reporting',
                'secret_key' => 'analytics_secret_' . bin2hex(random_bytes(16)),
                'is_active' => true,
            ]
        ];
        
        foreach ($endpoints as $endpointData) {
            Endpoint::factory()
                ->for($user)
                ->create($endpointData);
        }
        
        $this->command->info('Created ' . count($endpoints) . ' endpoints.');
        
        // Get all created endpoints
        $createdEndpoints = Endpoint::where('user_id', $user->id)->get();
        
        $this->command->info('Creating webhook events...');
        
        // Create events and deliveries
        $eventTypes = [
            'user.created', 'user.updated', 'user.deleted',
            'order.created', 'order.updated', 'order.cancelled', 'order.fulfilled',
            'payment.success', 'payment.failed', 'payment.refunded',
            'product.created', 'product.updated', 'product.deleted',
            'subscription.created', 'subscription.cancelled', 'subscription.renewed'
        ];
        
        $totalEvents = 0;
        $totalDeliveries = 0;
        
        foreach ($eventTypes as $eventType) {
            // Create 3-8 events of each type
            $eventCount = rand(3, 8);
            
            for ($i = 0; $i < $eventCount; $i++) {
                $event = Event::factory()
                    ->for($user)
                    ->create([
                        'name' => $eventType . '_' . time() . '_' . rand(1000, 9999),
                        'event_type' => $eventType,
                        'description' => 'Triggered when ' . str_replace('.', ' ', $eventType) . ' occurs',
                    ]);
                
                // Attach this event to 1-3 random endpoints
                $selectedEndpoints = $createdEndpoints->random(rand(1, min(3, $createdEndpoints->count())));
                $event->endpoints()->attach($selectedEndpoints->pluck('id'));
                
                // Create deliveries for each attached endpoint
                foreach ($selectedEndpoints as $endpoint) {
                    // Create 1-5 delivery attempts for this event-endpoint combination
                    $deliveryCount = rand(1, 5);
                    
                    for ($j = 0; $j < $deliveryCount; $j++) {
                        Delivery::factory()
                            ->for($event)
                            ->for($endpoint)
                            ->create();
                        $totalDeliveries++;
                    }
                }
                
                $totalEvents++;
            }
        }
        
        $this->command->info("Created {$totalEvents} events.");
        $this->command->info("Created {$totalDeliveries} deliveries.");
        
        // Create some recent activity (last 7 days)
        $this->command->info('Creating recent activity...');
        
        $recentEventCount = 15;
        $recentEvents = collect();
        
        for ($i = 0; $i < $recentEventCount; $i++) {
            $eventType = fake()->randomElement($eventTypes);
            $createdAt = now()->subDays(rand(0, 7));
            
            $event = Event::factory()
                ->for($user)
                ->create([
                    'name' => $eventType . '_recent_' . time() . '_' . rand(1000, 9999),
                    'event_type' => $eventType,
                    'description' => 'Recent ' . str_replace('.', ' ', $eventType) . ' event',
                    'created_at' => $createdAt,
                ]);
                
            $recentEvents->push($event);
        }
            
        foreach ($recentEvents as $event) {
            $selectedEndpoints = $createdEndpoints->random(rand(1, 2));
            $event->endpoints()->attach($selectedEndpoints->pluck('id'));
            
            foreach ($selectedEndpoints as $endpoint) {
                // Mix of successful and failed recent deliveries
                if (rand(0, 100) < 80) { // 80% success rate
                    Delivery::factory()
                        ->for($event)
                        ->for($endpoint)
                        ->successful()
                        ->create([
                            'created_at' => $event->created_at->addMinutes(rand(1, 10)),
                        ]);
                } else {
                    Delivery::factory()
                        ->for($event)
                        ->for($endpoint)
                        ->failed()
                        ->create([
                            'created_at' => $event->created_at->addMinutes(rand(1, 10)),
                        ]);
                }
            }
        }
        
        $this->command->info('Created recent activity.');
        
        // Display summary
        $this->command->info('\n=== Seeding Summary ===');
        $this->command->info('Endpoints: ' . Endpoint::count());
        $this->command->info('Events: ' . Event::count());
        $this->command->info('Deliveries: ' . Delivery::count());
        $this->command->info('Success rate: ' . round((Delivery::where('status', 'success')->count() / Delivery::count()) * 100, 1) . '%');
    }
}
