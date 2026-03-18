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
        Schema::table('lead_forms', function (Blueprint $table): void {
            if (! Schema::hasColumn('lead_forms', 'conversion_settings')) {
                $table->json('conversion_settings')->nullable()->after('field_schema');
            }
        });

        Schema::table('lead_form_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('lead_form_submissions', 'status')) {
                $table->string('status', 20)->default('new')->after('contact_id');
            }

            if (! Schema::hasColumn('lead_form_submissions', 'entity_id')) {
                $table->foreignId('entity_id')->nullable()->after('contact_id')->constrained('entities')->nullOnDelete();
            }

            if (! Schema::hasColumn('lead_form_submissions', 'deal_id')) {
                $table->foreignId('deal_id')->nullable()->after('entity_id')->constrained('deals')->nullOnDelete();
            }

            if (! Schema::hasColumn('lead_form_submissions', 'converted_at')) {
                $table->timestamp('converted_at')->nullable()->after('deal_id');
            }

            if (! Schema::hasColumn('lead_form_submissions', 'converted_by')) {
                $table->foreignId('converted_by')->nullable()->after('converted_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('lead_form_submissions', 'ignored_at')) {
                $table->timestamp('ignored_at')->nullable()->after('converted_by');
            }

            if (! Schema::hasColumn('lead_form_submissions', 'ignored_by')) {
                $table->foreignId('ignored_by')->nullable()->after('ignored_at')->constrained('users')->nullOnDelete();
            }

            $table->index(['tenant_id', 'status'], 'lead_submissions_tenant_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_form_submissions', function (Blueprint $table): void {
            if (Schema::hasColumn('lead_form_submissions', 'ignored_by')) {
                $table->dropForeign(['ignored_by']);
                $table->dropColumn('ignored_by');
            }

            if (Schema::hasColumn('lead_form_submissions', 'ignored_at')) {
                $table->dropColumn('ignored_at');
            }

            if (Schema::hasColumn('lead_form_submissions', 'converted_by')) {
                $table->dropForeign(['converted_by']);
                $table->dropColumn('converted_by');
            }

            if (Schema::hasColumn('lead_form_submissions', 'converted_at')) {
                $table->dropColumn('converted_at');
            }

            if (Schema::hasColumn('lead_form_submissions', 'deal_id')) {
                $table->dropForeign(['deal_id']);
                $table->dropColumn('deal_id');
            }

            if (Schema::hasColumn('lead_form_submissions', 'entity_id')) {
                $table->dropForeign(['entity_id']);
                $table->dropColumn('entity_id');
            }

            if (Schema::hasColumn('lead_form_submissions', 'status')) {
                $table->dropColumn('status');
            }

            $table->dropIndex('lead_submissions_tenant_status_index');
        });

        Schema::table('lead_forms', function (Blueprint $table): void {
            if (Schema::hasColumn('lead_forms', 'conversion_settings')) {
                $table->dropColumn('conversion_settings');
            }
        });
    }
};

