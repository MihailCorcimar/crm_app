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
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->date('event_date');
            $table->time('event_time');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->string('share')->nullable();
            $table->string('knowledge')->nullable();
            $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('calendar_type_id')->nullable()->constrained('calendar_types')->nullOnDelete();
            $table->foreignId('calendar_action_id')->nullable()->constrained('calendar_actions')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
