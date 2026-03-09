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
        Schema::table('deal_email_logs', function (Blueprint $table): void {
            $table->string('from_email')->nullable()->after('to_email');
            $table->string('tracking_token', 64)->nullable()->after('attachment_name');

            $table->index(['tenant_id', 'tracking_token'], 'deal_email_logs_tenant_tracking_idx');
            $table->index(['tenant_id', 'to_email', 'email_type', 'sent_at'], 'deal_email_logs_tenant_to_type_sent_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deal_email_logs', function (Blueprint $table): void {
            $table->dropIndex('deal_email_logs_tenant_tracking_idx');
            $table->dropIndex('deal_email_logs_tenant_to_type_sent_idx');
            $table->dropColumn(['from_email', 'tracking_token']);
        });
    }
};
