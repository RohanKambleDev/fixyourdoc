<?php

use App\Rules\ValidDocx;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * @param  array<string, string>  $entries  Archive path => contents.
 */
function zipUpload(array $entries): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    foreach ($entries as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    return UploadedFile::fake()->createWithContent('paper.docx', file_get_contents($path));
}

function passesValidDocx(mixed $file): bool
{
    return Validator::make(['file' => $file], ['file' => [new ValidDocx]])->passes();
}

test('a zip containing word/document.xml is accepted', function () {
    expect(passesValidDocx(zipUpload(['word/document.xml' => '<w:document/>'])))->toBeTrue();
});

test('a file that is not a zip is rejected', function () {
    expect(passesValidDocx(UploadedFile::fake()->createWithContent('paper.docx', 'plain text')))->toBeFalse();
});

test('a zip without word/document.xml is rejected', function () {
    expect(passesValidDocx(zipUpload(['notes.txt' => 'hello'])))->toBeFalse();
});

test('a value that is not an upload is rejected', function () {
    expect(passesValidDocx('paper.docx'))->toBeFalse();
});

test('an archive that unpacks to too much data is rejected', function () {
    config(['docxformatter.max_uncompressed_mb' => 1]);

    $file = zipUpload([
        'word/document.xml' => '<w:document/>',
        'word/media/huge.bin' => str_repeat('A', 2 * 1024 * 1024),
    ]);

    expect(passesValidDocx($file))->toBeFalse();
});

test('an archive with too many entries is rejected', function () {
    $entries = ['word/document.xml' => '<w:document/>'];

    foreach (range(1, 5000) as $i) {
        $entries["word/part-{$i}.xml"] = '';
    }

    expect(passesValidDocx(zipUpload($entries)))->toBeFalse();
});
