<?php

namespace Tests\Feature;

use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_can_get_all_users()
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'first_name',
                            'last_name',
                            'phone',
                            'email',
                            'emails'
                        ]
                    ]
                ]);
    }

    public function test_can_create_user()
    {
        $userData = [
            'first_name' => 'Ivan',
            'last_name' => 'Petrenko',
            'phone' => '+48991234567',
            'email' => 'ivan@example.com',
            'password' => 'password123',
            'emails' => [
                'ivan.work@example.com',
                'ivan.personal@example.com'
            ]
        ];

        $response = $this->postJson('/api/users', $userData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'User created successfully'
                ]);

        $this->assertDatabaseHas('users', [
            'first_name' => 'Ivan',
            'last_name' => 'Petrenko',
            'phone' => '+48991234567',
            'email' => 'ivan@example.com'
        ]);

        $this->assertDatabaseHas('user_emails', [
            'email' => 'ivan.work@example.com'
        ]);

        $this->assertDatabaseHas('user_emails', [
            'email' => 'ivan.personal@example.com'
        ]);
    }

    public function test_cannot_create_user_with_invalid_data()
    {
        $userData = [
            'first_name' => '',
            'last_name' => '',
            'phone' => 'invalid-phone',
            'email' => 'invalid-email',
            'password' => '123'
        ];

        $response = $this->postJson('/api/users', $userData);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'errors'
                ]);
    }

    public function test_can_get_user()
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/users/{$user->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_user()
    {
        $response = $this->getJson('/api/users/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'User not found'
                ]);
    }

    public function test_can_update_user()
    {
        $user = User::factory()->create();

        $updateData = [
            'first_name' => 'Maria',
            'last_name' => 'Ivanenko',
            'phone' => '+48992345678',
            'emails' => [
                'maria.new@example.com'
            ]
        ];

        $response = $this->putJson("/api/users/{$user->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'User updated successfully'
                ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Maria',
            'last_name' => 'Ivanenko',
            'phone' => '+48992345678'
        ]);

        $this->assertDatabaseHas('user_emails', [
            'user_id' => $user->id,
            'email' => 'maria.new@example.com'
        ]);
    }

    public function test_can_delete_user()
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id
        ]);
    }

    public function test_can_send_welcome_email()
    {
        Mail::fake();

        $user = User::factory()->create([
            'first_name' => 'Olena',
            'email' => 'olena@example.com'
        ]);

        UserEmail::create([
            'user_id' => $user->id,
            'email' => 'olena.work@example.com'
        ]);

        UserEmail::create([
            'user_id' => $user->id,
            'email' => 'olena.personal@example.com'
        ]);

        $response = $this->postJson("/api/users/{$user->id}/send-welcome-email");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Welcome emails sent to all user addresses',
                    'emails_sent' => 3
                ]);

        Mail::assertSent(WelcomeEmail::class, 3);
    }

    public function test_send_welcome_email_returns_404_for_nonexistent_user()
    {
        $response = $this->postJson('/api/users/999/send-welcome-email');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'User not found'
                ]);
    }

    public function test_cannot_create_user_with_duplicate_phone()
    {
        // Create first user
        $user1 = User::factory()->create([
            'phone' => '+380991234567'
        ]);

        // Try to create second user with the same phone
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+380991234567', // Duplicate
            'email' => 'john@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/users', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['phone']);
    }

    public function test_cannot_create_user_with_duplicate_email()
    {
        // Create first user
        $user1 = User::factory()->create([
            'email' => 'test@example.com'
        ]);

        // Try to create second user with the same email
        $userData = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '+380992345678',
            'email' => 'test@example.com', // Duplicate
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/users', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    public function test_cannot_create_user_with_duplicate_additional_emails()
    {
        // Create first user with additional emails
        $user1 = User::factory()->create();
        UserEmail::create([
            'user_id' => $user1->id,
            'email' => 'work@example.com'
        ]);

        // Try to create second user with the same additional email
        $userData = [
            'first_name' => 'Bob',
            'last_name' => 'Smith',
            'phone' => '+380993456789',
            'email' => 'bob@example.com',
            'password' => 'password123',
            'emails' => [
                'work@example.com' // Duplicate
            ]
        ];

        $response = $this->postJson('/api/users', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['emails.0']);
    }

    public function test_cannot_update_user_with_duplicate_phone()
    {
        // Create two users
        $user1 = User::factory()->create([
            'phone' => '+380991234567'
        ]);
        $user2 = User::factory()->create([
            'phone' => '+380992345678'
        ]);

        // Try to update second user with the same phone of the first
        $updateData = [
            'phone' => '+380991234567' // Duplicate
        ];

        $response = $this->putJson("/api/users/{$user2->id}", $updateData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['phone']);
    }

    public function test_cannot_update_user_with_duplicate_email()
    {
        // Create two users
        $user1 = User::factory()->create([
            'email' => 'test@example.com'
        ]);
        $user2 = User::factory()->create([
            'email' => 'other@example.com'
        ]);

        // Try to update second user with the same email of the first
        $updateData = [
            'email' => 'test@example.com' // Duplicate
        ];

        $response = $this->putJson("/api/users/{$user2->id}", $updateData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    public function test_can_update_user_with_same_phone()
    {
        // Create user
        $user = User::factory()->create([
            'phone' => '+380991234567'
        ]);

        // Update user with the same phone (should work)
        $updateData = [
            'phone' => '+380991234567' // Same phone
        ];

        $response = $this->putJson("/api/users/{$user->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'User updated successfully'
                ]);
    }

    public function test_can_update_user_with_same_email()
    {
        // Create user
        $user = User::factory()->create([
            'email' => 'test@example.com'
        ]);

        // Update user with the same email (should work)
        $updateData = [
            'email' => 'test@example.com' // Same email
        ];

        $response = $this->putJson("/api/users/{$user->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'User updated successfully'
                ]);
    }
}
