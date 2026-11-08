<?php

use App\Services\DocxFormatter;

/**
 * Formats samples/sample.docx with the real Python formatter and returns
 * queryable XML for the document body and the first footer.
 *
 * @param  array<string, mixed>  $specOverride  Merged over samples/spec.json.
 * @return array{document: DOMXPath, footer: DOMXPath}
 */
function formattedSample(bool $forceRuns = false, array $specOverride = []): array
{
    $output = tempnam(sys_get_temp_dir(), 'formatted');
    $spec = array_replace_recursive(json_decode(file_get_contents(base_path('samples/spec.json')), true), $specOverride);

    app(DocxFormatter::class)->format(base_path('samples/sample.docx'), $spec, $output, $forceRuns);

    $zip = new ZipArchive;
    $zip->open($output);
    $parts = ['document' => $zip->getFromName('word/document.xml'), 'footer' => $zip->getFromName('word/footer1.xml')];
    $zip->close();
    unlink($output);

    return array_map(function (string $xml): DOMXPath {
        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');

        return $xpath;
    }, $parts);
}

beforeEach(function () {
    if (! is_file(config('docxformatter.python')) || ! is_file(base_path('samples/sample.docx'))) {
        $this->markTestSkipped('Needs scripts/venv and samples/sample.docx.');
    }
});

// --- force runs

test('without force runs a manual font is kept', function () {
    expect(formattedSample(false)['document']->query('//w:rFonts[@w:ascii="Comic Sans MS"]')->length)->toBeGreaterThan(0);
});

test('with force runs a manual font is removed', function () {
    expect(formattedSample(true)['document']->query('//w:rFonts[@w:ascii="Comic Sans MS"]')->length)->toBe(0);
});

test('with force runs headings keep their style size instead of the body size', function () {
    $headingRunSizes = formattedSample(true)['document']->query(
        '//w:p[w:pPr/w:pStyle[@w:val="Title" or @w:val="Heading1" or @w:val="Heading2"]]//w:r/w:rPr/w:sz',
    );

    expect($headingRunSizes->length)->toBe(0);
});

test('with force runs table text keeps the size from the spec', function () {
    expect(formattedSample(true)['document']->query('//w:tbl//w:r[not(w:rPr/w:sz/@w:val="22")]')->length)->toBe(0);
});

// --- images

test('a shrunk image keeps its proportions', function () {
    $extent = formattedSample()['document']->query('//wp:inline/wp:extent')->item(0);

    // The sample picture is 3:1.
    expect($extent->getAttribute('cx') / $extent->getAttribute('cy'))->toEqualWithDelta(3.0, 0.01);
});

// --- table width

test('a table is set to the usable page width', function () {
    $width = formattedSample()['document']->query('//w:tbl/w:tblPr/w:tblW[@w:type="dxa"]/@w:w')->item(0)->nodeValue;

    // A4 (11905.5 twips) minus the 1.5 in and 1 in margins (3600 twips).
    expect((float) $width)->toEqualWithDelta(8305.5, 1.0);
});

test('a table can be given a fixed width', function () {
    $width = formattedSample(false, ['tables' => ['preferred_width' => '5in']])['document']
        ->query('//w:tbl/w:tblPr/w:tblW[@w:type="dxa"]/@w:w')->item(0)->nodeValue;

    expect((int) $width)->toBe(7200);
});

// --- alignment

test('references are left aligned when the spec says left', function () {
    $notLeft = formattedSample()['document']->query(
        '//w:p[w:pPr/w:pStyle/@w:val="ListNumber"][not(w:pPr/w:jc/@w:val="left")]',
    );

    expect($notLeft->length)->toBe(0);
});

test('page numbers are left aligned when the spec says left', function () {
    $footer = formattedSample(false, ['page_numbers' => ['alignment' => 'left']])['footer'];

    expect($footer->query('//w:p/w:pPr/w:jc/@w:val')->item(0)->nodeValue)->toBe('left');
});

test('page numbers are centred when the spec says centre', function () {
    expect(formattedSample()['footer']->query('//w:p/w:pPr/w:jc/@w:val')->item(0)->nodeValue)->toBe('center');
});
