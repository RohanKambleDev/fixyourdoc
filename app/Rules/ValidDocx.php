<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Checks that an upload is a real .docx: a zip archive containing
 * word/document.xml whose contents are not absurdly large. Nothing is extracted.
 */
class ValidDocx implements ValidationRule
{
    private const MAX_ENTRIES = 5000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $zip = new ZipArchive;

        if (! $value instanceof UploadedFile || $zip->open($value->getRealPath(), ZipArchive::RDONLY) !== true) {
            $fail('The :attribute must be a valid .docx file.');

            return;
        }

        $maxBytes = config('docxformatter.max_uncompressed_mb') * 1024 * 1024;
        $totalBytes = 0;

        for ($i = 0; $i < min($zip->numFiles, self::MAX_ENTRIES); $i++) {
            $totalBytes += $zip->statIndex($i)['size'] ?? 0;
        }

        $isDocx = $zip->locateName('word/document.xml') !== false;
        $isTooBig = $zip->numFiles > self::MAX_ENTRIES || $totalBytes > $maxBytes;

        $zip->close();

        if (! $isDocx) {
            $fail('The :attribute must be a valid .docx file.');
        } elseif ($isTooBig) {
            $fail('The :attribute is too large once unpacked.');
        }
    }
}
