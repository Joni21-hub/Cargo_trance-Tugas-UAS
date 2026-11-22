<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Users: Operator branch assignment (location_id) & simulated virtual wallet balance
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('role')->constrained('locations')->nullOnDelete();
            $table->decimal('wallet_balance_usd', 12, 2)->default(0)->after('location_id');
        });

        // 2. Tracking events: optional explicit coordinates per milestone
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('location_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        // 3. Shipment exceptions: explicit detection timestamp
        Schema::table('shipment_exceptions', function (Blueprint $table) {
            $table->dateTime('detected_at')->nullable()->after('status');
        });

        // 4. In-app Notifications (Required by UAS Brief Slide 10 & 11)
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->onDelete('cascade');
            $table->string('type'); // SHIPMENT_CREATED, SHIPMENT_CONFIRMED, TRACKING_UPDATE, EXCEPTION_ALERT, ESCROW_UPDATE, DELIVERED
            $table->string('title');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });

        // 5. Simulated Deterministic Escrow Transactions
        Schema::create('escrow_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained('shipments')->onDelete('cascade');
            $table->foreignId('shipper_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount_usd', 12, 2)->default(0);
            $table->string('status')->default('PENDING_DEPOSIT'); // PENDING_DEPOSIT, HELD_IN_ESCROW, FROZEN_ON_EXCEPTION, RELEASED, DISPUTED, REFUNDED
            $table->dateTime('deposited_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // 6. Simulated Wallet Mutations (Top-up, Escrow Deposit, Escrow Release, Withdrawal)
        Schema::create('wallet_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->string('type'); // TOPUP, ESCROW_DEPOSIT, ESCROW_RELEASE, ESCROW_REFUND, WITHDRAWAL
            $table->decimal('amount_usd', 12, 2);
            $table->decimal('balance_after_usd', 12, 2);
            $table->string('reference_code')->unique();
            $table->string('description');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_mutations');
        Schema::dropIfExists('escrow_transactions');
        Schema::dropIfExists('notifications');

        Schema::table('shipment_exceptions', function (Blueprint $table) {
            $table->dropColumn('detected_at');
        });

        Schema::table('tracking_events', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn(['location_id', 'wallet_balance_usd']);
        });
    }
};
