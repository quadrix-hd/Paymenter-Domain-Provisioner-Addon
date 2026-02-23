<?php

namespace Paymenter\Extensions\Others\DomainProvisioner\Models;

use Illuminate\Database\Eloquent\Model;

class DomainSetting extends Model
{
    protected $table    = 'domain_provisioner_settings';
    protected $fillable = ['key', 'value'];
}
