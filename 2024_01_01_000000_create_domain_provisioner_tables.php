<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('domain_provisioner_settings')) {
            Schema::create('domain_provisioner_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });

            $defaults = [
                'pangolin_url'       => '',
                'pangolin_api_key'   => '',
                'pangolin_org_id'    => '',
                'pangolin_site_id'   => '',
                'pangolin_domain_id' => '',
                'subdomain_field'    => 'subdomain',
                'target_port'        => '80',
            ];
            foreach ($defaults as $key => $value) {
                DB::table('domain_provisioner_settings')->insert([
                    'key'        => $key,
                    'value'      => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (!Schema::hasTable('domain_provisions')) {
            Schema::create('domain_provisions', function (Blueprint $table) {
                $table->id();
                $table->string('order_id')->unique()->index();
                $table->string('full_domain');
                $table->string('server_ip');
                $table->string('cf_record_id')->nullable();
                $table->string('pangolin_resource_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_provisions');
        Schema::dropIfExists('domain_provisioner_settings');
    }
};
