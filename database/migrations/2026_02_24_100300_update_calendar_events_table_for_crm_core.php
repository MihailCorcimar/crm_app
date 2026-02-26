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
        Schema::table('calendar_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('calendar_events', 'eventable_type')) {
                $table->nullableMorphs('eventable');
            }

            if (! Schema::hasColumn('calendar_events', 'title')) {
                $table->string('title')->nullable();
            }

            if (! Schema::hasColumn('calendar_events', 'start_at')) {
                $table->timestamp('start_at')->nullable();
            }

            if (! Schema::hasColumn('calendar_events', 'end_at')) {
                $table->timestamp('end_at')->nullable();
            }

            if (! Schema::hasColumn('calendar_events', 'location')) {
                $table->string('location')->nullable();
            }

            if (! Schema::hasColumn('calendar_events', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('calendar_events', 'tenant_id') && Schema::hasColumn('calendar_events', 'start_at')) {
            Schema::table('calendar_events', function (Blueprint $table): void {
                $table->index(['tenant_id', 'start_at'], 'calendar_events_tenant_start_at_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('calendar_events', 'tenant_id') && Schema::hasColumn('calendar_events', 'start_at')) {
            Schema::table('calendar_events', function (Blueprint $table): void {
                $table->dropIndex('calendar_events_tenant_start_at_index');
            });
        }

        Schema::table('calendar_events', function (Blueprint $table): void {
            if (Schema::hasColumn('calendar_events', 'owner_id')) {
                $table->dropConstrainedForeignId('owner_id');
            }

            $toDrop = [];
            foreach (['title', 'start_at', 'end_at', 'location'] as $column) {
                if (Schema::hasColumn('calendar_events', $column)) {
                    $toDrop[] = $column;
                }
            }

            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }

            if (Schema::hasColumn('calendar_events', 'eventable_type')) {
                $table->dropMorphs('eventable');
            }
        });
    }
};
