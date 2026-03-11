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
        Schema::create('lead_forms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->string('status', 20)->default('active');
            $table->boolean('requires_captcha')->default(true);
            $table->text('confirmation_message');
            $table->json('field_schema');
            $table->string('embed_token', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug'], 'lead_forms_tenant_slug_unique');
            $table->index(['tenant_id', 'status'], 'lead_forms_tenant_status_index');
        });

        Schema::create('lead_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_form_id')->constrained('lead_forms')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('source_type', 40)->default('public_page');
            $table->text('source_url')->nullable();
            $table->string('source_origin', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('captcha_passed')->default(false);
            $table->json('payload');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['tenant_id', 'submitted_at'], 'lead_submissions_tenant_submitted_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_form_submissions');
        Schema::dropIfExists('lead_forms');
    }
};

