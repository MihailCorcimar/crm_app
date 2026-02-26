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
        Schema::create('calendar_event_attendees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->string('attendee_type');
            $table->unsignedBigInteger('attendee_id');
            $table->timestamps();

            $table->index(['attendee_type', 'attendee_id'], 'calendar_event_attendee_lookup');
            $table->unique(['calendar_event_id', 'attendee_type', 'attendee_id'], 'calendar_event_attendee_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_event_attendees');
    }
};
