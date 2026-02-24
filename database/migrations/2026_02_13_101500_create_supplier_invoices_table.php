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
        Schema::create('supplier_invoices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('number')->unique();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->foreignId('supplier_id')->constrained('entities');
            $table->foreignId('supplier_order_id')->nullable()->constrained('supplier_orders')->nullOnDelete();
            $table->decimal('total', 12, 2)->default(0);
            $table->string('document_path')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->string('status', 30)->default('pending_payment');
            $table->timestamp('proof_emailed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};

