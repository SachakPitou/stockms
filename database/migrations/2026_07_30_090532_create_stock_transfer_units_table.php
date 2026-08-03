<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_unit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['stock_transfer_id', 'equipment_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_units');
    }
};