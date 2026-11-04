<?php

namespace App\Jobs;

use App\Enums\FormatJobStatus;
use App\Models\FormatJob;
use App\Services\DocxFormatter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FormatDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    // The user may delete a job from the history page while it is queued.
    public bool $deleteWhenMissingModels = true;

    public function __construct(public FormatJob $formatJob)
    {
        $this->timeout = config('docxformatter.timeout_seconds') + 30;
    }

    public function handle(DocxFormatter $formatter): void
    {
        $disk = Storage::disk(config('docxformatter.disk'));

        $this->formatJob->update(['status' => FormatJobStatus::Processing, 'error' => null]);

        $outputPath = dirname($this->formatJob->input_path).'/'.Str::uuid().'.docx';

        try {
            $formatter->format(
                $disk->path($this->formatJob->input_path),
                $this->formatJob->spec,
                $disk->path($outputPath),
                $this->formatJob->force_runs,
            );

            $this->formatJob->update(['status' => FormatJobStatus::Done, 'output_path' => $outputPath]);
        } catch (RuntimeException $e) {
            $this->formatJob->update(['status' => FormatJobStatus::Failed, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Called for failures not handled above, such as a worker timeout.
     */
    public function failed(Throwable $exception): void
    {
        $this->formatJob->update([
            'status' => FormatJobStatus::Failed,
            'error' => 'Formatting failed unexpectedly. Please try again.',
        ]);
    }
}
