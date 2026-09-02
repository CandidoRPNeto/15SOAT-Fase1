<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3: rotas protegidas por `auth:sanctum,client_jwt` também aceitam um
 * JWT (RS256) como o que a Function Serverless de auth por CPF emite —
 * aditivo ao Sanctum, ver AppServiceProvider::resolveUserFromClientJwt() e
 * docs/architecture/rfcs/rfc-003-cpf-auth-strategy.md.
 *
 * Gera um par de chaves RS256 real por teste (openssl, ver setUp) em vez
 * de mockar a verificação de assinatura — exercita a validação de verdade,
 * não só a lógica em torno dela.
 */
class ClientJwtAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        // openssl_pkey_export() recebe o 2º argumento por referência — não
        // aceita escrever direto numa typed property ainda não
        // inicializada, daí a variável local.
        openssl_pkey_export($keyPair, $privateKey);
        $this->privateKey = $privateKey;
        $this->publicKey = openssl_pkey_get_details($keyPair)['key'];

        config([
            'services.client_jwt.public_key' => $this->publicKey,
            'services.client_jwt.issuer' => 'workshop-os-lambda-auth',
        ]);
    }

    private function mintToken(array $overrides = []): string
    {
        $claims = array_merge([
            'iss' => 'workshop-os-lambda-auth',
            'iat' => time(),
            'exp' => time() + 300,
        ], $overrides);

        return JWT::encode($claims, $this->privateKey, 'RS256');
    }

    public function test_a_valid_token_authenticates_the_client(): void
    {
        $client = User::factory()->client()->create();
        $token = $this->mintToken(['sub' => $client->id]);

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()->assertJson(['id' => $client->id]);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $client = User::factory()->client()->create();
        $token = $this->mintToken(['sub' => $client->id, 'exp' => time() - 10]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_a_token_with_the_wrong_issuer_is_rejected(): void
    {
        $client = User::factory()->client()->create();
        $token = $this->mintToken(['sub' => $client->id, 'iss' => 'someone-else']);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_a_token_for_a_blocked_client_is_rejected(): void
    {
        $client = User::factory()->client()->blocked()->create();
        $token = $this->mintToken(['sub' => $client->id]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_a_token_for_a_non_client_role_is_rejected(): void
    {
        // O Lambda só emite token pra cliente — se um sub apontar pra um
        // receptionist/mechanic (não deveria acontecer, mas o guard não
        // confia cegamente no claim), o guard nega mesmo assim.
        $staff = User::factory()->receptionist()->create();
        $token = $this->mintToken(['sub' => $staff->id]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_a_token_signed_with_a_different_key_is_rejected(): void
    {
        $client = User::factory()->client()->create();

        $otherKeyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($otherKeyPair, $otherPrivateKey);

        $token = JWT::encode([
            'sub' => $client->id,
            'iss' => 'workshop-os-lambda-auth',
            'iat' => time(),
            'exp' => time() + 300,
        ], $otherPrivateKey, 'RS256');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_a_token_for_a_nonexistent_user_is_rejected(): void
    {
        $token = $this->mintToken(['sub' => 999999]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}
