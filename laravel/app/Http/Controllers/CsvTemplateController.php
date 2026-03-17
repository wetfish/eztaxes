<?php

namespace App\Http\Controllers;

use App\Models\CsvTemplate;

class CsvTemplateController extends Controller
{
    public function index()
    {
        $templates = CsvTemplate::orderBy('name')->get();

        return view('csv-templates.index', compact('templates'));
    }

    public function destroy(int $id)
    {
        $template = CsvTemplate::findOrFail($id);
        $template->delete();

        return redirect('/csv-templates')->with('success', "Template '{$template->name}' deleted.");
    }
}