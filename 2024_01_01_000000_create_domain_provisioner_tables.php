<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_provisioner_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('domain_provisions', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique()->index();
            $table->string('full_domain');
            $table->string('server_ip');
            $table->string('cf_record_id')->nullable();
            $table->string('pangolin_resource_id')->nullable();
            $table->timestamps();
        });

        // Standard-Einstellungen einfügen
        $defaults = [
            'base_domain'     => 'example.com',
            'subdomain_field' => 'subdomain',
            'server_ip_field' => 'server_ip',
            'target_port'     => '80',
            'cf_proxied'      => '0',
        ];
        foreach ($defaults as $key => $value) {
            DB::table('domain_provisioner_settings')->insertOrIgnore(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_provisions');
        Schema::dropIfExists('domain_provisioner_settings');
    }
};
