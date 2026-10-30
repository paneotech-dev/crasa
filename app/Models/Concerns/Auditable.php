<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Writes an audit row on create, update and delete.
 *
 * On update only the attributes that actually changed are stored, each with
 * its before and after value. A full snapshot on every edit bloats the table
 * and buries the one thing a reader is looking for.
 *
 * Secrets are never written to the trail. Passwords, two-factor secrets and
 * remember tokens are redacted by name, so a future column that matches is
 * caught by default rather than leaking on the day it is added.
 */
trait Auditable
{
    /** @var list<string> */
    protected static array $auditRedacted = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            AuditLog::record(AuditLog::CREATED, $model, [
                'attributes' => $model->auditableAttributes($model->getAttributes()),
            ]);
        });

        static::updated(function ($model): void {
            $changes = $model->auditableChanges();

            // A save that changed nothing of substance is not an event.
            if ($changes === []) {
                return;
            }

            AuditLog::record(AuditLog::UPDATED, $model, $changes);
        });

        static::deleted(function ($model): void {
            AuditLog::record(AuditLog::DELETED, $model, [
                'attributes' => $model->auditableAttributes($model->getOriginal()),
            ]);
        });
    }

    /**
     * Only what changed, with before and after.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function auditableChanges(): array
    {
        $changes = [];

        foreach ($this->getChanges() as $key => $new) {
            if (in_array($key, $this->auditIgnored(), true)) {
                continue;
            }

            $redacted = static::auditRedacts($key);

            $changes[$key] = [
                'from' => $redacted ? '[redacted]' : $this->getOriginal($key),
                'to' => $redacted ? '[redacted]' : $new,
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditableAttributes(array $attributes): array
    {
        foreach (array_keys($attributes) as $key) {
            if (in_array($key, $this->auditIgnored(), true)) {
                unset($attributes[$key]);

                continue;
            }

            if (static::auditRedacts($key)) {
                $attributes[$key] = '[redacted]';
            }
        }

        return $attributes;
    }

    /**
     * A secret, by name or by pattern: a password, a token, a two-factor
     * secret or recovery code (Day 3 morning, 1g).
     */
    protected static function auditRedacts(string $key): bool
    {
        return in_array($key, static::$auditRedacted, true)
            || (bool) preg_match('/password|token|secret|recovery_code/i', $key);
    }

    /**
     * Columns that carry no information a reader of the trail would want.
     *
     * @return list<string>
     */
    protected function auditIgnored(): array
    {
        return ['updated_at', 'last_seen_at'];
    }
}
