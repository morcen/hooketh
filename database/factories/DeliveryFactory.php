<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Delivery>
 */
class DeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['success', 'failed', 'pending', 'retrying']);
        $responseCode = null;
        $responseBody = null;
        $deliveredAt = null;
        $nextRetryAt = null;
        $attemptCount = $this->faker->numberBetween(1, 5);
        
        // Set realistic response codes and bodies based on status
        switch ($status) {
            case 'success':
                $responseCode = $this->faker->randomElement([200, 201, 202]);
                $responseBody = json_encode(['status' => 'ok', 'message' => 'Webhook received successfully']);
                $deliveredAt = $this->faker->dateTimeBetween('-30 days', 'now');
                break;
                
            case 'failed':
                $responseCode = $this->faker->randomElement([400, 404, 500, 502, 503]);
                $responseBody = $this->generateErrorResponse($responseCode);
                $nextRetryAt = $this->faker->dateTimeBetween('now', '+2 hours');
                break;
                
            case 'pending':
                $attemptCount = 0;
                break;
                
            case 'retrying':
                $responseCode = $this->faker->randomElement([500, 502, 503, 504]);
                $responseBody = $this->generateErrorResponse($responseCode);
                $nextRetryAt = $this->faker->dateTimeBetween('now', '+1 hour');
                break;
        }
        
        return [
            'payload' => $this->generateRealisticPayload(),
            'status' => $status,
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'attempt_count' => $attemptCount,
            'delivered_at' => $deliveredAt,
            'next_retry_at' => $nextRetryAt,
        ];
    }
    
    private function generateRealisticPayload(): array
    {
        $payloadTypes = [
            // User payload
            [
                'event' => 'user.created',
                'data' => [
                    'id' => $this->faker->numberBetween(1, 10000),
                    'email' => $this->faker->safeEmail(),
                    'name' => $this->faker->name(),
                    'created_at' => $this->faker->iso8601(),
                ]
            ],
            // Order payload
            [
                'event' => 'order.created',
                'data' => [
                    'id' => $this->faker->numberBetween(1, 10000),
                    'customer_id' => $this->faker->numberBetween(1, 1000),
                    'total' => $this->faker->randomFloat(2, 10, 1000),
                    'currency' => 'USD',
                    'status' => 'pending',
                    'items' => [
                        [
                            'id' => $this->faker->numberBetween(1, 100),
                            'name' => $this->faker->words(3, true),
                            'quantity' => $this->faker->numberBetween(1, 5),
                            'price' => $this->faker->randomFloat(2, 5, 200),
                        ]
                    ],
                ]
            ],
            // Payment payload
            [
                'event' => 'payment.success',
                'data' => [
                    'id' => $this->faker->uuid(),
                    'amount' => $this->faker->randomFloat(2, 1, 500),
                    'currency' => 'USD',
                    'method' => $this->faker->randomElement(['credit_card', 'paypal', 'bank_transfer']),
                    'status' => 'completed',
                    'processed_at' => $this->faker->iso8601(),
                ]
            ],
        ];
        
        $payload = $this->faker->randomElement($payloadTypes);
        $payload['timestamp'] = $this->faker->iso8601();
        $payload['webhook_id'] = $this->faker->uuid();
        
        return $payload;
    }
    
    private function generateErrorResponse(int $code): string
    {
        $errorMessages = [
            400 => ['error' => 'Bad Request', 'message' => 'Invalid payload format'],
            404 => ['error' => 'Not Found', 'message' => 'Webhook endpoint not found'],
            500 => ['error' => 'Internal Server Error', 'message' => 'Server encountered an error'],
            502 => ['error' => 'Bad Gateway', 'message' => 'Invalid response from upstream server'],
            503 => ['error' => 'Service Unavailable', 'message' => 'Service temporarily unavailable'],
            504 => ['error' => 'Gateway Timeout', 'message' => 'Request timeout'],
        ];
        
        return json_encode($errorMessages[$code] ?? ['error' => 'Unknown Error', 'message' => 'An unknown error occurred']);
    }
    
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'response_code' => 200,
            'response_body' => json_encode(['status' => 'ok', 'message' => 'Webhook received successfully']),
            'delivered_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'next_retry_at' => null,
        ]);
    }
    
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'response_code' => $this->faker->randomElement([400, 500, 502]),
            'response_body' => json_encode(['error' => 'Internal Server Error', 'message' => 'Server encountered an error']),
            'delivered_at' => null,
            'next_retry_at' => $this->faker->dateTimeBetween('now', '+2 hours'),
        ]);
    }
}
