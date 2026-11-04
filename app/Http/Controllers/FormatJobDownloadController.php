<?php

namespace App\Http\Controllers;

use App\Models\FormatJob;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormatJobDownloadController extends Controller
{
    public function __invoke(FormatJob $formatJob, string $type): StreamedResponse
    {
        Gate::authorize('view', $formatJob);

        $path = $type === 'formatted' ? $formatJob->output_path : $formatJob->input_path;
        $name = $type === 'formatted' ? 'formatted-'.$formatJob->original_name : $formatJob->original_name;

        $disk = Storage::disk(config('docxformatter.disk'));

        abort_unless($path && $disk->exists($path), 404);

        return $disk->download($path, $name);
    }
}
