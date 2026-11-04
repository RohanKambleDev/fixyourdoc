<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validation rules and an example for the formatting spec read by
 * scripts/docx_formatter.py. Only keys the script actually uses are allowed.
 */
final class FormatSpec
{
    private const LENGTH = '/^\s*(\d*\.?\d+)\s*(in|inch|inches|mm|millimeters?|cm|centimeters?)?\s*$/i';

    private const PAGE_OR_LENGTH = '/^\s*(page|(\d*\.?\d+)\s*(in|inch|inches|mm|millimeters?|cm|centimeters?)?)\s*$/i';

    private const LINE_SPACING = '/^\s*(single|double|onehalf|one-half|\d+(\.\d+)?)\s*$/i';

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        $alignment = ['nullable', 'in:left,center,right,justify'];
        $name = ['string', 'max:100'];
        $points = ['numeric', 'min:0', 'max:200'];
        $fontSize = ['numeric', 'min:1', 'max:72'];

        return [
            'page' => ['nullable', 'array'],
            'page.size' => ['nullable', 'in:A4,Letter'],
            'page.margins' => ['nullable', 'array'],
            'page.margins.top' => ['nullable', 'regex:'.self::LENGTH],
            'page.margins.bottom' => ['nullable', 'regex:'.self::LENGTH],
            'page.margins.left' => ['nullable', 'regex:'.self::LENGTH],
            'page.margins.right' => ['nullable', 'regex:'.self::LENGTH],

            'font' => ['nullable', 'array'],
            'font.family' => ['nullable', ...$name],
            'font.size_pt' => ['nullable', ...$fontSize],
            'font.apply_to' => ['nullable', 'array', 'max:50'],
            'font.apply_to.*' => $name,

            'paragraph' => ['nullable', 'array'],
            'paragraph.line_spacing' => ['nullable', 'regex:'.self::LINE_SPACING],
            'paragraph.space_before_pt' => ['nullable', ...$points],
            'paragraph.space_after_pt' => ['nullable', ...$points],
            'paragraph.alignment' => $alignment,

            'headings' => ['nullable', 'array'],
            'headings.font_family' => ['nullable', ...$name],
            'headings.size_overrides_pt' => ['nullable', 'array', 'max:50'],
            'headings.size_overrides_pt.*' => $fontSize,
            'headings.bold' => ['nullable', 'array', 'max:50'],
            'headings.bold.*' => $name,

            'page_numbers' => ['nullable', 'array'],
            'page_numbers.enabled' => ['nullable', 'boolean'],
            'page_numbers.position' => ['nullable', 'in:header,footer'],
            'page_numbers.alignment' => ['nullable', 'in:left,center,right'],
            'page_numbers.start_at' => ['nullable', 'integer', 'min:1', 'max:100000'],

            'images' => ['nullable', 'array'],
            'images.max_width' => ['nullable', 'regex:'.self::PAGE_OR_LENGTH],
            'images.center' => ['nullable', 'boolean'],

            'tables' => ['nullable', 'array'],
            'tables.auto_fit' => ['nullable', 'boolean'],
            'tables.preferred_width' => ['nullable', 'regex:'.self::PAGE_OR_LENGTH],
            'tables.alignment' => ['nullable', 'in:left,center,right'],
            'tables.font_family' => ['nullable', ...$name],
            'tables.size_pt' => ['nullable', ...$fontSize],

            'references' => ['nullable', 'array'],
            'references.enabled' => ['nullable', 'boolean'],
            'references.titles' => ['nullable', 'array', 'max:20'],
            'references.titles.*' => $name,
            'references.apply_numbering_style' => ['nullable', 'boolean'],
            'references.numbering_style' => ['nullable', ...$name],
            'references.hanging_indent' => ['nullable', 'regex:'.self::LENGTH],
            'references.line_spacing' => ['nullable', 'regex:'.self::LINE_SPACING],
            'references.space_before_pt' => ['nullable', ...$points],
            'references.space_after_pt' => ['nullable', ...$points],
            'references.alignment' => $alignment,
            'references.stop_at_next_heading' => ['nullable', 'boolean'],

            'cleanup' => ['nullable', 'array'],
            'cleanup.remove_empty_paragraphs' => ['nullable', 'boolean'],
            'cleanup.collapse_multiple_newlines' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Validate a spec, rejecting any key the formatter does not read.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $spec): array
    {
        $rules = self::rules();

        $validator = Validator::make($spec, $rules);

        $validator->after(function ($validator) use ($spec, $rules): void {
            foreach (array_keys(Arr::dot($spec)) as $path) {
                $known = collect(array_keys($rules))->contains(fn (string $rule) => Str::is($rule, (string) $path));

                if (! $known) {
                    $validator->errors()->add((string) $path, "Unknown setting [{$path}].");
                }
            }
        });

        return $validator->validate();
    }

    /**
     * Turn form input into what the formatter expects: drop blank values and
     * convert numeric strings (point sizes, start numbers) to numbers.
     *
     * @param  array<array-key, mixed>  $spec
     * @return array<array-key, mixed>
     */
    public static function normalize(array $spec, ?string $parent = null): array
    {
        $normalized = [];

        foreach ($spec as $key => $value) {
            if (is_array($value)) {
                $value = self::normalize($value, (string) $key);
            } elseif (is_string($value) && is_numeric($value) && (
                str_ends_with((string) $key, '_pt') || $key === 'start_at' || $parent === 'size_overrides_pt'
            )) {
                $value += 0;
            }

            if ($value !== null && $value !== '' && $value !== []) {
                $normalized[$key] = $value;
            }
        }

        return array_is_list($spec) ? array_values($normalized) : $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public static function example(): array
    {
        return [
            'page' => [
                'size' => 'A4',
                'margins' => ['top' => '1in', 'bottom' => '1in', 'left' => '1in', 'right' => '1in'],
            ],
            'font' => [
                'family' => 'Times New Roman',
                'size_pt' => 12,
                'apply_to' => ['Normal', '*'],
            ],
            'paragraph' => [
                'line_spacing' => 'double',
                'space_before_pt' => 0,
                'space_after_pt' => 0,
                'alignment' => 'justify',
            ],
            'headings' => [
                'font_family' => 'Times New Roman',
                'size_overrides_pt' => ['Title' => 14, 'Heading 1' => 14, 'Heading 2' => 12],
                'bold' => ['Heading 1', 'Heading 2'],
            ],
            'page_numbers' => [
                'enabled' => true,
                'position' => 'footer',
                'alignment' => 'center',
                'start_at' => 1,
            ],
            'images' => ['max_width' => 'page', 'center' => true],
            'tables' => [
                'auto_fit' => true,
                'preferred_width' => 'page',
                'alignment' => 'center',
                'font_family' => 'Times New Roman',
                'size_pt' => 12,
            ],
            'references' => [
                'enabled' => true,
                'titles' => ['References', 'Bibliography', 'Works Cited'],
                'apply_numbering_style' => true,
                'numbering_style' => 'List Number',
                'hanging_indent' => '0.5in',
                'line_spacing' => 'double',
                'space_before_pt' => 0,
                'space_after_pt' => 0,
                'alignment' => 'left',
                'stop_at_next_heading' => true,
            ],
            'cleanup' => [
                'remove_empty_paragraphs' => true,
                'collapse_multiple_newlines' => true,
            ],
        ];
    }
}
