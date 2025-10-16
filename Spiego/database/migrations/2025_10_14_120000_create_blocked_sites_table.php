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
        Schema::create('blocked_sites', function (Blueprint $table) {
            $table->id();
            $table->string('hostname')->index();
            $table->string('domain');
            $table->timestamps();
            $table->unique(['hostname', 'domain']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_sites');
    }
};
