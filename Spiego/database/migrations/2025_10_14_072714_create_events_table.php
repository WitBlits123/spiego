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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp')->useCurrent();
            $table->string('event_type'); // foreground_change, mouse_active, mouse_idle, key_count, metadata
            $table->string('hostname');
            $table->json('data'); // All event-specific data as JSON
            $table->timestamps();
            
            $table->index('timestamp');
            $table->index('event_type');
            $table->index('hostname');
            $table->index(['hostname', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
