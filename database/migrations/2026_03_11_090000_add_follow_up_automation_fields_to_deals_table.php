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
        Schema::table('deals', function (Blueprint $table): void {
            $table->boolean('follow_up_active')->default(false)->after('proposal_uploaded_by');
            $table->timestamp('follow_up_started_at')->nullable()->after('follow_up_active');
            $table->timestamp('follow_up_next_send_at')->nullable()->after('follow_up_started_at');
            $table->timestamp('follow_up_last_sent_at')->nullable()->after('follow_up_next_send_at');
            $table->unsignedTinyInteger('follow_up_template_index')->default(0)->after('follow_up_last_sent_at');
            $table->timestamp('follow_up_customer_replied_at')->nullable()->after('follow_up_template_index');
            $table->timestamp('follow_up_stopped_at')->nullable()->after('follow_up_customer_replied_at');
            $table->string('follow_up_stop_reason', 50)->nullable()->after('follow_up_stopped_at');

            $table->index(['follow_up_active', 'follow_up_next_send_at'], 'deals_follow_up_due_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->dropIndex('deals_follow_up_due_index');
            $table->dropColumn([
                'follow_up_active',
                'follow_up_started_at',
                'follow_up_next_send_at',
                'follow_up_last_sent_at',
                'follow_up_template_index',
                'follow_up_customer_replied_at',
                'follow_up_stopped_at',
                'follow_up_stop_reason',
            ]);
        });
    }
};
