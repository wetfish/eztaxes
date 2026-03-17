<?php

namespace App\Http\Controllers;

use App\Models\TaxYear;

class DashboardController extends Controller
{
    public function index()
    {
        $taxYears = TaxYear::orderBy('year', 'desc')->get();

        return view('dashboard.index', compact('taxYears'));
    }
}