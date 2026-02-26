<?php

namespace App\Http\Controllers;

use App\Exports\EventsExport;
use App\Imports\EventsImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class EventCsvController extends Controller
{
    public function export()
    {
        return Excel::download(new EventsExport, 'events.csv');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);
        Excel::import(new EventsImport, $request->file('file'));
        return back()->with('success', 'Import zakończony!');
    }
}
