<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_and_authenticated_company_user_can_view_private_profile_photo(): void
    {
        $this->seed();
        Storage::fake('local');

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->patch(route('builder360.profile-photo.update'), [
                'photo' => $this->png('aditya.png'),
            ])
            ->assertRedirect(route('builder360.profile'));

        $director->refresh();
        $this->assertNotNull($director->profile_photo_path);
        Storage::disk('local')->assertExists($director->profile_photo_path);

        $photoResponse = $this->actingAs($sales)
            ->get(route('builder360.profile-photo.show', $director))
            ->assertOk();

        $this->assertStringContainsString('inline', (string) $photoResponse->headers->get('Content-Disposition'));

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $director->id,
            'event_type' => 'profile.photo.updated',
        ]);
    }

    public function test_chat_uses_profile_photos_and_keeps_initials_as_fallback(): void
    {
        $this->seed();
        Storage::fake('local');

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $this->actingAs($finance)->patch(route('builder360.profile-photo.update'), [
            'photo' => $this->png('suresh.png'),
        ])->assertRedirect();

        $response = $this->actingAs($sales)->postJson(route('collaboration.chat.conversations.store'), [
            'type' => 'direct_message',
            'member_user_ids' => [$finance->id],
            'body' => 'Profile image rendering check.',
        ])->assertCreated();

        $conversation = ChatConversation::findOrFail($response->json('data.id'));

        $this->actingAs($sales)
            ->get(route('collaboration.chat.index', ['conversation_id' => $conversation->id]))
            ->assertOk()
            ->assertSee(route('builder360.profile-photo.show', $finance), false)
            ->assertSee('b360-user-avatar-image', false)
            ->assertSee('b360-message-avatar', false);
    }

    public function test_profile_photo_rejects_unsupported_files(): void
    {
        $this->seed();
        Storage::fake('local');

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->patch(route('builder360.profile-photo.update'), [
                'photo' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg></svg>'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($director->refresh()->profile_photo_path);
    }

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
    }
}
