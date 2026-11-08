<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Runs scripts/docx_formatter.py to format a .docx according to a spec.
 */
class DocxFormatter
{
    /**
     * @param  array<string, mixed>  $spec  Already validated with FormatSpec::validate().
     *
     * @throws RuntimeException When the script fails or produces no output.
     */
    public function format(string $inputPath, array $spec, string $outputPath, bool $forceRuns = false): void
    {
        if (! is_readable($inputPath)) {
            Log::warning('docx formatter input is not readable', [
                'path' => $inputPath,
                'exists' => file_exists($inputPath),
                'running_as' => function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? null) : null,
            ]);

            throw new RuntimeException('The uploaded document could not be read. Please upload it again.');
        }

        $specPath = tempnam(sys_get_temp_dir(), 'docx-spec-');

        if ($specPath === false) {
            throw new RuntimeException('Could not create a temporary spec file.');
        }

        try {
            file_put_contents($specPath, json_encode($spec, JSON_THROW_ON_ERROR));

            $command = [
                config('docxformatter.python'),
                config('docxformatter.script'),
                '--input', $inputPath,
                '--spec', $specPath,
                '--output', $outputPath,
            ];

            if ($forceRuns) {
                $command[] = '--force-runs';
            }

            $result = Process::timeout(config('docxformatter.timeout_seconds'))->run($command);

            if ($result->failed()) {
                Log::warning('docx formatter failed', [
                    'exit_code' => $result->exitCode(),
                    'stderr' => $result->errorOutput(),
                ]);

                throw new RuntimeException('The document could not be formatted. It may be corrupt or not a valid .docx file.');
            }

            if (! is_file($outputPath)) {
                throw new RuntimeException('The formatter finished but produced no file.');
            }
        } finally {
            @unlink($specPath);
        }
    }
}
