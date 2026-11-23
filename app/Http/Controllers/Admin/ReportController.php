<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalesReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    // RQ-029, RQ-030, RQ-031: laporan penjualan per rentang tanggal
    public function sales(Request $request)
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ], [
            'start_date.date'         => 'Tanggal mulai tidak valid.',
            'end_date.date'           => 'Tanggal akhir tidak valid.',
            'end_date.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
        ]);

        // Default: awal bulan ini sampai hari ini
        $start = $request->filled('start_date') ? Carbon::parse($request->start_date) : now()->startOfMonth();
        $end = $request->filled('end_date') ? Carbon::parse($request->end_date) : now();

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        $report = SalesReport::generate($start, $end);

        return view('admin.reports.sales', compact('report'));
    }
}
