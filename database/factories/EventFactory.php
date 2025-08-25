<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = [
            // User events
            ['name' => 'user.created', 'description' => 'Triggered when a new user registers'],
            ['name' => 'user.updated', 'description' => 'Triggered when user profile is updated'],
            ['name' => 'user.deleted', 'description' => 'Triggered when a user account is deleted'],
            ['name' => 'user.login', 'description' => 'Triggered when user successfully logs in'],
            ['name' => 'user.logout', 'description' => 'Triggered when user logs out'],
            
            // Order events
            ['name' => 'order.created', 'description' => 'Triggered when a new order is placed'],
            ['name' => 'order.updated', 'description' => 'Triggered when order status changes'],
            ['name' => 'order.shipped', 'description' => 'Triggered when order is shipped'],
            ['name' => 'order.delivered', 'description' => 'Triggered when order is delivered'],
            ['name' => 'order.cancelled', 'description' => 'Triggered when order is cancelled'],
            ['name' => 'order.refunded', 'description' => 'Triggered when order is refunded'],
            
            // Payment events
            ['name' => 'payment.success', 'description' => 'Triggered when payment is successful'],
            ['name' => 'payment.failed', 'description' => 'Triggered when payment fails'],
            ['name' => 'payment.refunded', 'description' => 'Triggered when payment is refunded'],
            ['name' => 'subscription.created', 'description' => 'Triggered when subscription is created'],
            ['name' => 'subscription.cancelled', 'description' => 'Triggered when subscription is cancelled'],
            
            // System events
            ['name' => 'system.maintenance', 'description' => 'Triggered during system maintenance'],
            ['name' => 'system.backup', 'description' => 'Triggered when system backup completes'],
            ['name' => 'security.breach', 'description' => 'Triggered when security issue is detected'],
            
            // Content events
            ['name' => 'post.published', 'description' => 'Triggered when a post is published'],
            ['name' => 'comment.added', 'description' => 'Triggered when a comment is added'],
            ['name' => 'file.uploaded', 'description' => 'Triggered when a file is uploaded'],
        ];
        
        $event = $this->faker->randomElement($eventTypes);
        
        return [
            'name' => $event['name'],
            'description' => $event['description'],
        ];
    }
}
