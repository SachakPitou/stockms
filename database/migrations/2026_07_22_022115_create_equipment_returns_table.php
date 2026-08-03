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
        Schema::create('equipment_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issuance_id')->constrained('equipment_issuances');
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('received_by')->constrained('users');
            $table->integer('quantity')->default(1);
            $table->date('return_date');
            $table->enum('condition', ['good', 'needs_repair', 'scrap'])->default('good');
            $table->enum('action', ['restock', 'repair', 'scrap'])->default('restock');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_returns');
    }
};
