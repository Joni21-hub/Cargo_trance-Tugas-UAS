<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique(); // CTG-YYYYMMDD-XXXX
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('carrier_id')->constrained('carriers')->onDelete('cascade');
            $table->foreignId('origin_location_id')->constrained('locations');
            $table->foreignId('destination_location_id')->constrained('locations');
            $table->foreignId('current_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('status')->default('CREATED'); // CREATED, BOOKED, PICKED_UP, IN_TRANSIT, DELIVERED, CANCELLED
            $table->string('cargo_description');
            $table->decimal('weight_kg', 10, 2);
            $table->decimal('volume_cbm', 10, 2)->nullable();
            $table->dateTime('expected_departure');
            $table->dateTime('expected_arrival');
            $table->dateTime('calculated_eta');
            $table->boolean('is_delayed')->default(false);
            $table->integer('delay_hours')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('tracking_number');
            $table->index('status');
            $table->index('is_delayed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
