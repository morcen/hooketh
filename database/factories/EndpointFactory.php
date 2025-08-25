<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Endpoint>
 */
class EndpointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            'Slack', 'Discord', 'Teams', 'Zapier', 'Shopify', 'Stripe', 
            'PayPal', 'Mailchimp', 'SendGrid', 'Twilio', 'GitHub', 'GitLab'
        ];
        
        $service = $this->faker->randomElement($services);
        
        return [
            'name' => $service . ' ' . $this->faker->randomElement(['Integration', 'Webhook', 'Notifications', 'API']),
            'url' => $this->generateRealisticUrl($service),
            'secret_key' => $this->faker->sha256(),
            'description' => $this->faker->sentence(10),
            'is_active' => $this->faker->boolean(85), // 85% chance of being active
        ];
    }
    
    private function generateRealisticUrl(string $service): string
    {
        $domains = [
            'Slack' => 'hooks.slack.com',
            'Discord' => 'discord.com',
            'Teams' => 'outlook.office.com',
            'Zapier' => 'hooks.zapier.com',
            'Shopify' => 'myshop.myshopify.com',
            'Stripe' => 'api.stripe.com',
            'PayPal' => 'api.paypal.com',
            'Mailchimp' => 'us1.api.mailchimp.com',
            'SendGrid' => 'api.sendgrid.com',
            'Twilio' => 'api.twilio.com',
            'GitHub' => 'api.github.com',
            'GitLab' => 'gitlab.com',
        ];
        
        $domain = $domains[$service] ?? $this->faker->domainName();
        $path = '/webhook/' . $this->faker->uuid();
        
        return 'https://' . $domain . $path;
    }
    
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
