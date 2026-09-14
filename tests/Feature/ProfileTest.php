<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response
            ->assertOk()
            ->assertSeeVolt('profile.update-profile-information-form')
            ->assertSeeVolt('profile.update-password-form')
            ->assertSeeVolt('profile.active-sessions-form')
            ->assertSeeVolt('profile.delete-user-form');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_user_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('name', $user->name)
            ->set('email', $user->email)
            ->set('foto', UploadedFile::fake()->image('foto.jpg'))
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->hasMedia('avatar'));
    }

    public function test_la_foto_de_perfil_debe_ser_una_imagen(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('name', $user->name)
            ->set('email', $user->email)
            ->set('foto', UploadedFile::fake()->create('documento.pdf', 100))
            ->call('updateProfileInformation')
            ->assertHasErrors('foto');

        $this->assertFalse($user->fresh()->hasMedia('avatar'));
    }

    public function test_el_usuario_puede_quitar_su_foto_de_perfil(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->addMediaFromString('contenido-de-prueba')
            ->usingFileName('foto.jpg')
            ->toMediaCollection('avatar');

        $this->assertTrue($user->fresh()->hasMedia('avatar'));

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->call('quitarFoto')
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->hasMedia('avatar'));
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $component
            ->assertHasErrors('password')
            ->assertNoRedirect();

        $this->assertNotNull($user->fresh());
    }
}
