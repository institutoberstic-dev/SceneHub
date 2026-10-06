<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_LINK_MESSAGE = 'Si el correo pertenece a una cuenta activa, recibirás un enlace para restablecer tu contraseña. Revisa también la carpeta de spam.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(array $attributes = []): User
    {
        $user = User::factory()->create([
            'email' => 'persona@example.com',
            'password' => Hash::make('ClaveActual123'),
            'is_active' => true,
            ...$attributes,
        ]);
        $user->assignRole('usuario');

        return $user;
    }

    public function test_user_changes_password_from_profile(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/perfil')->assertOk()->assertViewIs('app');

        $this->putJson('/perfil/contrasena', [
            'current_password' => 'ClaveActual123',
            'password' => 'ClaveNueva456',
            'password_confirmation' => 'ClaveNueva456',
        ])->assertOk()->assertJsonPath('message', 'Tu contraseña fue actualizada correctamente.');

        $this->assertTrue(Hash::check('ClaveNueva456', $user->fresh()->password));
    }

    public function test_profile_password_change_validates_current_password_and_confirmation(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->putJson('/perfil/contrasena', [
            'current_password' => 'Incorrecta999',
            'password' => 'ClaveNueva456',
            'password_confirmation' => 'ClaveNueva456',
        ])->assertUnprocessable()->assertJsonPath('errors.current_password.0', 'La contraseña actual no es correcta.');

        $this->putJson('/perfil/contrasena', [
            'current_password' => 'ClaveActual123',
            'password' => 'ClaveNueva456',
            'password_confirmation' => 'OtraDistinta789',
        ])->assertUnprocessable()->assertJsonPath('errors.password.0', 'La confirmación no coincide con la nueva contraseña.');

        $this->putJson('/perfil/contrasena', [
            'current_password' => 'ClaveActual123',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('ClaveActual123', $user->fresh()->password));
    }

    public function test_guests_cannot_change_password_from_profile(): void
    {
        $this->putJson('/perfil/contrasena', [
            'current_password' => 'ClaveActual123',
            'password' => 'ClaveNueva456',
            'password_confirmation' => 'ClaveNueva456',
        ])->assertUnauthorized();
    }

    public function test_password_recovery_pages_are_public(): void
    {
        $this->get('/recuperar-contrasena')->assertOk()->assertViewIs('app');
        $this->get('/restablecer-contrasena/token-de-prueba?email=persona@example.com')->assertOk()->assertViewIs('app');
    }

    public function test_reset_link_is_sent_only_to_active_accounts_with_the_same_response(): void
    {
        Notification::fake();
        $active = $this->user();
        $disabled = $this->user(['email' => 'inactiva@example.com', 'is_active' => false]);

        foreach (['persona@example.com', 'inactiva@example.com', 'no-existe@example.com'] as $email) {
            $this->postJson('/recuperar-contrasena', ['email' => $email])
                ->assertOk()
                ->assertJsonPath('message', self::GENERIC_LINK_MESSAGE);
        }

        Notification::assertSentTo($active, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($active): bool {
            $mail = $notification->toMail($active);

            return $mail->subject === 'Restablece tu contraseña de SceneHub'
                && str_contains($mail->actionUrl, '/restablecer-contrasena/')
                && str_contains($mail->actionUrl, 'email=persona%40example.com');
        });
        Notification::assertNotSentTo($disabled, ResetPasswordNotification::class);
        Notification::assertSentTimes(ResetPasswordNotification::class, 1);
    }

    public function test_user_resets_password_with_a_valid_token_and_can_log_in(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $this->postJson('/restablecer-contrasena', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ClaveNueva456',
            'password_confirmation' => 'ClaveNueva456',
        ])->assertOk()->assertJsonPath('message', 'Tu contraseña fue restablecida. Ya puedes iniciar sesión.');

        $this->assertTrue(Hash::check('ClaveNueva456', $user->fresh()->password));

        // El token se consume: no puede reutilizarse.
        $this->postJson('/restablecer-contrasena', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'OtraClave789',
            'password_confirmation' => 'OtraClave789',
        ])->assertUnprocessable();

        $this->postJson('/log-in', ['email' => $user->email, 'password' => 'ClaveNueva456'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_invalid_tokens_and_disabled_accounts_cannot_reset_password(): void
    {
        $user = $this->user();

        $this->postJson('/restablecer-contrasena', [
            'token' => 'token-invalido',
            'email' => $user->email,
            'password' => 'ClaveNueva456',
            'password_confirmation' => 'ClaveNueva456',
        ])->assertUnprocessable()->assertJsonPath('message', 'El enlace no es válido o ya expiró. Solicita uno nuevo.');

        $disabled = $this->user(['email' => 'inactiva@example.com', 'is_active' => false]);
        $token = Password::createToken($disabled);

        $this->postJson('/restablecer-contrasena', [
            'token' => $token,
            'email' => $disabled->email,
            'password' => 'ClaveNueva456',
            'password_confirmation' => 'ClaveNueva456',
        ])->assertUnprocessable()->assertJsonPath('message', 'El enlace no es válido o ya expiró. Solicita uno nuevo.');

        $this->assertTrue(Hash::check('ClaveActual123', $user->fresh()->password));
        $this->assertTrue(Hash::check('ClaveActual123', $disabled->fresh()->password));
    }
}
