<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah buyer_id ke tabel shipments
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('buyer_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
        });

        // 2. Buat tabel shipment_items (detail barang yang diinput oleh operator)
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->string('item_name');
            $table->string('category')->nullable(); // e.g. Kopi, Kakao, Rempah, Tekstil
            $table->integer('quantity')->default(1);
            $table->decimal('weight_kg', 10, 2)->default(0);
            $table->string('condition')->default('BAIK'); // BAIK, SEGEL UTUH, DLL
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['buyer_id']);
            $table->dropColumn('buyer_id');
        });
    }
};
