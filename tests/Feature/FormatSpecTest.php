<?php

use App\Support\FormatSpec;
use Illuminate\Validation\ValidationException;

test('the example spec is valid', function () {
    expect(FormatSpec::validate(FormatSpec::example()))->toEqual(FormatSpec::example());
});

test('an empty spec is valid', function () {
    expect(FormatSpec::validate([]))->toBe([]);
});

test('valid values are accepted', function (array $spec) {
    expect(fn () => FormatSpec::validate($spec))->not->toThrow(ValidationException::class);
})->with([
    'millimetres' => [['page' => ['margins' => ['left' => '25mm']]]],
    'centimetres with a space' => [['page' => ['margins' => ['left' => '1.5 cm']]]],
    'bare number means inches' => [['page' => ['margins' => ['left' => 2]]]],
    'decimal line spacing' => [['paragraph' => ['line_spacing' => '1.5']]],
    'numeric line spacing' => [['paragraph' => ['line_spacing' => 2]]],
    'image width of page' => [['images' => ['max_width' => 'page']]],
    'image width as length' => [['images' => ['max_width' => '6in']]],
    'false booleans' => [['page_numbers' => ['enabled' => false]]],
    'zero spacing' => [['paragraph' => ['space_before_pt' => 0]]],
]);

test('invalid values are rejected', function (array $spec, string $field) {
    try {
        FormatSpec::validate($spec);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);

        return;
    }

    $this->fail("Expected [{$field}] to be rejected.");
})->with([
    'unknown unit' => [['page' => ['margins' => ['left' => '2 parsecs']]], 'page.margins.left'],
    'unknown page size' => [['page' => ['size' => 'A0']], 'page.size'],
    'bad alignment' => [['paragraph' => ['alignment' => 'diagonal']], 'paragraph.alignment'],
    'bad line spacing' => [['paragraph' => ['line_spacing' => 'triple']], 'paragraph.line_spacing'],
    'font too big' => [['font' => ['size_pt' => 500]], 'font.size_pt'],
    'font size not a number' => [['font' => ['size_pt' => 'big']], 'font.size_pt'],
    'font name too long' => [['font' => ['family' => str_repeat('x', 101)]], 'font.family'],
    'negative spacing' => [['paragraph' => ['space_after_pt' => -1]], 'paragraph.space_after_pt'],
    'page number start of zero' => [['page_numbers' => ['start_at' => 0]], 'page_numbers.start_at'],
    'bad image width' => [['images' => ['max_width' => 'wide']], 'images.max_width'],
    'bad heading size' => [['headings' => ['size_overrides_pt' => ['Title' => 'huge']]], 'headings.size_overrides_pt.Title'],
]);

test('settings the formatter does not read are rejected', function (array $spec, string $field) {
    try {
        FormatSpec::validate($spec);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);

        return;
    }

    $this->fail("Expected [{$field}] to be rejected.");
})->with([
    'unknown section' => [['evil' => ['cmd' => 'rm -rf /']], 'evil.cmd'],
    'unknown key in a known section' => [['page' => ['bleed' => '3mm']], 'page.bleed'],
    'checklist-only setting' => [['word_count' => ['min' => 2000]], 'word_count.min'],
]);

test('numeric strings from a form become numbers', function () {
    $spec = FormatSpec::normalize([
        'font' => ['size_pt' => '12', 'family' => '123'],
        'paragraph' => ['space_before_pt' => '0', 'space_after_pt' => '6.5'],
        'page_numbers' => ['start_at' => '3'],
        'headings' => ['size_overrides_pt' => ['Title' => '14']],
    ]);

    expect($spec)->toBe([
        'font' => ['size_pt' => 12, 'family' => '123'],
        'paragraph' => ['space_before_pt' => 0, 'space_after_pt' => 6.5],
        'page_numbers' => ['start_at' => 3],
        'headings' => ['size_overrides_pt' => ['Title' => 14]],
    ]);
});

test('lengths and names stay as text', function () {
    expect(FormatSpec::normalize(['page' => ['margins' => ['left' => '1', 'right' => '1in']]]))
        ->toBe(['page' => ['margins' => ['left' => '1', 'right' => '1in']]]);
});

test('blank values are dropped but false and zero are kept', function () {
    $spec = FormatSpec::normalize([
        'font' => ['family' => '', 'size_pt' => null],
        'page_numbers' => ['enabled' => false, 'start_at' => 0],
        'images' => [],
    ]);

    expect($spec)->toBe(['page_numbers' => ['enabled' => false, 'start_at' => 0]]);
});

test('lists stay lists after blank entries are removed', function () {
    $spec = FormatSpec::normalize(['headings' => ['bold' => ['', 'Heading 1', null, 'Heading 2']]]);

    expect(json_encode($spec))->toBe('{"headings":{"bold":["Heading 1","Heading 2"]}}');
});
