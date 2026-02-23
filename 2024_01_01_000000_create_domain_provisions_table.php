<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('domain_provisions');
    }
};
