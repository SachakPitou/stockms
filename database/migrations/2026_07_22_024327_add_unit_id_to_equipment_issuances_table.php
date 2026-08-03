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
        Schema::table('equipment_issuances', function (Blueprint $table) {
            $table->foreignId('equipment_unit_id')
                ->nullable()
                ->after('product_id')
                ->constrained('equipment_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_issuances', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\EquipmentUnit::class);
            $table->dropColumn('equipment_unit_id');
        });
    }
};
