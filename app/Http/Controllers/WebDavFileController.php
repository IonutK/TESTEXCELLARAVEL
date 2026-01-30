<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class WebDavFileController extends Controller
{
    public function list()
    {
        $root = storage_path('app/webdav');
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }

        $files = collect(File::files($root))
            ->map(fn ($file) => $file->getFilename())
            ->sort()
            ->values();

        return response()->json(['files' => $files]);
    }

    public function upload(Request $request)
    {
        $root = storage_path('app/webdav');
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:xlsx,xls,csv'],
        ]);

        $file = $request->file('file');
        $original = $file->getClientOriginalName();
        $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $original) ?: 'upload.xlsx';

        $file->move($root, $safeName);

        return response()->json(['file' => $safeName]);
    }
}
