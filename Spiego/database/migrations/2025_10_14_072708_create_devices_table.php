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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('hostname')->unique();
            $table->string('platform');
            $table->string('processor')->nullable();
            $table->integer('cpu_count')->nullable();
            $table->float('memory_total')->nullable(); // in bytes
            $table->text('mac_addresses')->nullable(); // JSON array
            $table->string('python_version')->nullable();
            $table->timestamp('last_seen');
            $table->timestamps();
            
            $table->index('hostname');
            $table->index('last_seen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
