<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->candidate()->create();
        $this->otherUser = User::factory()->candidate()->create();
    }

    // ---------------------------------------------------------------
    // Authorization / Access Control
    // ---------------------------------------------------------------

    public function test_guest_cannot_access_notification_endpoints(): void
    {
        $response = $this->getJson('/api/notifications');

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Listing
    // ---------------------------------------------------------------

    public function test_user_can_list_own_notifications(): void
    {
        Notification::factory()->count(3)->create(['user_id' => $this->user->id]);
        Notification::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_user_can_filter_notifications_by_read_status(): void
    {
        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        Notification::factory()->read()->create([
            'user_id' => $this->user->id,
        ]);

        $unreadResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications?is_read=0');

        $unreadResponse->assertStatus(200);
        $this->assertCount(2, $unreadResponse->json('data.data'));

        $readResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications?is_read=1');

        $readResponse->assertStatus(200);
        $this->assertCount(1, $readResponse->json('data.data'));
    }

    public function test_user_can_get_unread_count(): void
    {
        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        Notification::factory()->read()->create(['user_id' => $this->user->id]);
        Notification::factory()->create(['user_id' => $this->otherUser->id, 'is_read' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'unread_count' => 2]);
    }

    // ---------------------------------------------------------------
    // Marking read / unread
    // ---------------------------------------------------------------

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Notification marked as read.']);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => 1,
        ]);
    }

    public function test_user_can_mark_a_notification_as_unread(): void
    {
        $notification = Notification::factory()->read()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/notifications/{$notification->id}/unread");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Notification marked as unread.']);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => 0,
            'read_at' => null,
        ]);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->otherUser->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(403);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => 0,
        ]);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        $otherUnread = Notification::factory()->create([
            'user_id' => $this->otherUser->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/notifications/mark-all-read');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'All notifications marked as read.']);

        $this->assertDatabaseCount('notifications', 4);
        $this->assertSame(0, Notification::where('user_id', $this->user->id)->where('is_read', false)->count());

        $this->assertDatabaseHas('notifications', [
            'id' => $otherUnread->id,
            'is_read' => 0,
        ]);
    }

    // ---------------------------------------------------------------
    // Deleting
    // ---------------------------------------------------------------

    public function test_user_can_delete_own_notification(): void
    {
        $notification = Notification::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Notification deleted.']);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_user_cannot_delete_another_users_notification(): void
    {
        $notification = Notification::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/notifications/{$notification->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }
}
