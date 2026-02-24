<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->date('order_date')->nullable()->after('proposal_id');
            $table->date('valid_until')->nullable()->after('order_date');
        });

        DB::table('orders as o')
            ->leftJoin('proposals as p', 'p.id', '=', 'o.proposal_id')
            ->select([
                'o.id',
                'o.created_at',
                'o.order_date',
                'o.valid_until',
                'p.proposal_date',
                'p.valid_until as proposal_valid_until',
            ])
            ->where(function ($query): void {
                $query->whereNull('o.order_date')
                    ->orWhereNull('o.valid_until');
            })
            ->orderBy('o.id')
            ->get()
            ->each(function (object $row): void {
                $baseDate = $row->proposal_date !== null
                    ? CarbonImmutable::parse((string) $row->proposal_date)
                    : CarbonImmutable::parse((string) $row->created_at);

                $orderDate = $row->order_date ?? $baseDate->toDateString();
                $validUntil = $row->valid_until;

                if ($validUntil === null) {
                    $validUntil = $row->proposal_valid_until !== null
                        ? CarbonImmutable::parse((string) $row->proposal_valid_until)->toDateString()
                        : $baseDate->addDays(30)->toDateString();
                }

                DB::table('orders')
                    ->where('id', (int) $row->id)
                    ->update([
                        'order_date' => $orderDate,
                        'valid_until' => $validUntil,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['order_date', 'valid_until']);
        });
    }
};
