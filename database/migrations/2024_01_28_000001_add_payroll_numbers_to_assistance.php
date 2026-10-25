<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll numbers for assistance (Oct 2026).
 *
 *   assistance_allocations.payroll_no
 *       One per distribution batch, PAY-<year>-<4 digit sequence>, handed out
 *       by the system when the batch is finalised. Never typed by anyone.
 *
 *   assistance_allocation_beneficiaries.payroll_line
 *       The farmer's line on that batch's payroll, 1, 2, 3 ... in order of
 *       surname. Together they read PAY-2026-0003-007.
 *
 * Both are unique, so two batches (or two farmers on one batch) can never
 * share a number. Batches and designations that already exist are numbered
 * here, in the order they were made, so every record has one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistance_allocations', function (Blueprint $table) {
            $table->string('payroll_no', 20)->nullable()->unique()->after('batch_name');
        });

        Schema::table('assistance_allocation_beneficiaries', function (Blueprint $table) {
            $table->unsignedSmallInteger('payroll_line')->nullable()->after('damage_report_id');
        });

        // Existing batches, oldest first, numbered per year.
        $perYear = [];
        foreach (DB::table('assistance_allocations')->orderBy('id')->get(['id', 'allocated_at', 'created_at']) as $row) {
            $year = date('Y', strtotime((string) ($row->allocated_at ?? $row->created_at ?? 'now')));
            $perYear[$year] = ($perYear[$year] ?? 0) + 1;

            DB::table('assistance_allocations')->where('id', $row->id)->update([
                'payroll_no' => sprintf('PAY-%s-%04d', $year, $perYear[$year]),
            ]);
        }

        // Existing designations: a line each, by surname within the batch.
        $byBatch = DB::table('assistance_allocation_beneficiaries as b')
            ->join('farmers as f', 'f.id', '=', 'b.farmer_id')
            ->orderBy('b.assistance_allocation_id')
            ->orderBy('f.last_name')
            ->orderBy('f.first_name')
            ->orderBy('b.id')
            ->get(['b.id', 'b.assistance_allocation_id']);

        $line = [];
        foreach ($byBatch as $row) {
            $line[$row->assistance_allocation_id] = ($line[$row->assistance_allocation_id] ?? 0) + 1;
            DB::table('assistance_allocation_beneficiaries')->where('id', $row->id)
                ->update(['payroll_line' => $line[$row->assistance_allocation_id]]);
        }

        Schema::table('assistance_allocation_beneficiaries', function (Blueprint $table) {
            $table->unique(['assistance_allocation_id', 'payroll_line'], 'beneficiaries_payroll_line_unique');
        });
    }

    public function down(): void
    {
        Schema::table('assistance_allocation_beneficiaries', function (Blueprint $table) {
            $table->dropUnique('beneficiaries_payroll_line_unique');
            $table->dropColumn('payroll_line');
        });

        Schema::table('assistance_allocations', function (Blueprint $table) {
            $table->dropUnique(['payroll_no']);
            $table->dropColumn('payroll_no');
        });
    }
};
