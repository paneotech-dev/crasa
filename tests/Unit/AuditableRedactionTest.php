<?php

namespace Tests\Unit;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

/**
 * Redaction on write is permanent: it hides passwords, tokens and secrets
 * by name, and keeps columns that only contain one of those words.
 */
class AuditableRedactionTest extends TestCase
{
    public function test_secret_columns_are_hidden_and_harmless_lookalikes_kept(): void
    {
        $model = new class extends Model
        {
            use Auditable;
        };

        $out = $model->auditableAttributes([
            'secretariat' => 'CRASA Secretariat',
            'secretariat_email' => 'crasa@crasa.org',
            'password' => 'hunter2',
            'api_token' => 'tok-123',
            'two_factor_secret' => 'ABC',
            'two_factor_recovery_codes' => '[]',
            'recovery_code' => 'r-1',
            'remember_token' => 'rem',
        ]);

        $this->assertSame('CRASA Secretariat', $out['secretariat']);
        $this->assertSame('crasa@crasa.org', $out['secretariat_email']);

        foreach (['password', 'api_token', 'two_factor_secret', 'two_factor_recovery_codes', 'recovery_code', 'remember_token'] as $key) {
            $this->assertSame('[redacted]', $out[$key], $key);
        }
    }
}
