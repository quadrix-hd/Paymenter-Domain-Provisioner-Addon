<?php

namespace Paymenter\Extensions\Others\DomainProvisioner\Models;

use Illuminate\Database\Eloquent\Model;

class DomainSetting extends Model
{
    protected $table    = 'domain_provisioner_settings';
    protected $fillable = ['key', 'value'];

    /**
     * Hilfsfunktion: Einstellung per Key lesen.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    /**
     * Hilfsfunktion: Einstellung speichern.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
