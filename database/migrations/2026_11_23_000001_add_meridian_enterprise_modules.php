<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Users: Complete identity profile & Admin verification status
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('company_name', 160)->nullable()->after('phone');
            $table->text('address')->nullable()->after('company_name');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('country', 100)->nullable()->after('city');
            $table->string('identity_number', 80)->nullable()->after('country');
            $table->text('bio')->nullable()->after('identity_number');
            $table->boolean('is_verified')->default(false)->after('bio');
            $table->dateTime('verified_at')->nullable()->after('is_verified');
        });

        // 2. Locations: Island grouping for 5 major Indonesian islands + international hubs
        Schema::table('locations', function (Blueprint $table) {
            $table->string('island', 60)->nullable()->after('country');
        });

        // 3. Shipments: Multimodal transport, land pickup origin, physical verification checklist, digital release doc & day progress
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('transport_mode', 20)->default('SEA')->after('status'); // SEA, AIR, LAND
            $table->string('pickup_address', 255)->nullable()->after('transport_mode');
            $table->decimal('pickup_latitude', 10, 7)->nullable()->after('pickup_address');
            $table->decimal('pickup_longitude', 10, 7)->nullable()->after('pickup_latitude');
            $table->string('seal_number', 60)->nullable()->after('pickup_longitude');
            $table->json('verification_checklist')->nullable()->after('seal_number');
            $table->string('release_document_number', 80)->nullable()->after('verification_checklist');
            $table->string('release_document_type', 40)->nullable()->after('release_document_number');
            $table->string('release_courier_tracking', 80)->nullable()->after('release_document_type');
            $table->dateTime('release_issued_at')->nullable()->after('release_courier_tracking');
            $table->integer('simulated_days_elapsed')->default(0)->after('release_issued_at');
        });

        // 4. Nusantara Coffee Directory (Non-Marketplace: NO price column; Buyer negotiates via Chat)
        Schema::create('export_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipper_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('origin_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('name', 160);
            $table->string('island', 60); // Sumatra, Jawa, Kalimantan, Sulawesi, Papua
            $table->string('region', 160);
            $table->string('bean_type', 60)->default('Arabika'); // Arabika, Robusta, Liberika
            $table->string('process_method', 100)->nullable();
            $table->string('altitude_mdpl', 80)->nullable();
            $table->string('tasting_notes', 255)->nullable();
            $table->string('supply_capacity', 100)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['island', 'is_active']);
        });

        // 5. Shipper Pickup / Inspection Calls ("Buat Pengiriman" button -> calls Branch Operator to inspect goods)
        Schema::create('pickup_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code', 40)->unique();
            $table->foreignId('shipper_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('pickup_city', 120);
            $table->text('pickup_address');
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();
            $table->integer('target_days')->nullable();
            $table->string('preferred_mode', 20)->default('SEA'); // SEA or AIR
            $table->decimal('agreed_escrow_usd', 12, 2)->default(0);
            $table->string('commodity_summary', 255);
            $table->text('scheduled_note')->nullable();
            $table->string('status', 40)->default('PENDING_INSPECTION'); // PENDING_INSPECTION, OPERATOR_DISPATCHED, CONVERTED_TO_SHIPMENT, CANCELLED
            $table->foreignId('assigned_operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_location_id', 'status']);
        });

        // 6. Direct Messaging / Chat (Buyer <-> Shipper <-> Operator)
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel_type', 30)->default('DIRECT'); // DIRECT, SHIPMENT, PICKUP_REQUEST
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->onDelete('cascade');
            $table->foreignId('pickup_request_id')->nullable()->constrained('pickup_requests')->onDelete('cascade');
            $table->foreignId('export_product_id')->nullable()->constrained('export_products')->nullOnDelete();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('recipient_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['sender_id', 'recipient_id']);
            $table->index(['shipment_id', 'created_at']);
            $table->index(['pickup_request_id', 'created_at']);
        });

        // 7. Admin Help / Support Tickets ("Bantuan Admin" button across all pages)
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_code', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name', 120)->nullable();
            $table->string('guest_contact', 120)->nullable();
            $table->string('user_role', 40)->nullable();
            $table->string('category', 40)->default('UMUM'); // AKUN_VERIFIKASI, PENGIRIMAN, REKENING_JAMINAN, SENGKETA, UMUM
            $table->string('subject', 180);
            $table->text('message');
            $table->string('status', 30)->default('OPEN'); // OPEN, IN_PROGRESS, RESOLVED
            $table->text('admin_reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('replied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('pickup_requests');
        Schema::dropIfExists('export_products');

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'transport_mode',
                'pickup_address',
                'pickup_latitude',
                'pickup_longitude',
                'seal_number',
                'verification_checklist',
                'release_document_number',
                'release_document_type',
                'release_courier_tracking',
                'release_issued_at',
                'simulated_days_elapsed',
            ]);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('island');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'company_name',
                'address',
                'city',
                'country',
                'identity_number',
                'bio',
                'is_verified',
                'verified_at',
            ]);
        });
    }
};
