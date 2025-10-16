<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json
            $table->timestamps();
        });

        // Insert default settings
        DB::table('admin_settings')->insert([
            ['key' => 'admin_email', 'value' => '', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'notifications_enabled', 'value' => 'false', 'type' => 'boolean', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'idle_alert_enabled', 'value' => 'false', 'type' => 'boolean', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'idle_threshold_minutes', 'value' => '30', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'summary_report_enabled', 'value' => 'false', 'type' => 'boolean', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'summary_report_frequency', 'value' => 'daily', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'offline_alert_enabled', 'value' => 'false', 'type' => 'boolean', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'offline_threshold_minutes', 'value' => '60', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
    }
};
