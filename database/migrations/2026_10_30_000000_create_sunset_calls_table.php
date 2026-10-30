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
        Schema::create('sunset_calls', function (Blueprint $table) {
            $table->id();
            $table->string('route');
            $table->string('method', 10);
            $table->string('caller');
            $table->string('ip', 45)->nullable();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('first_called_at');
            $table->timestamp('last_called_at')->index();

            $table->unique(['route', 'method', 'caller']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sunset_calls');
    }
};
