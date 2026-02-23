<?php

namespace Paymenter\Extensions\Others\DomainProvisioner\Models;

use Illuminate\Database\Eloquent\Model;

class DomainProvision extends Model
{
    protected $table    = 'domain_provisions';
    protected $fillable = ['order_id', 'full_domain', 'server_ip', 'cf_record_id', 'pangolin_resource_id'];
}
