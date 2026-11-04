@php
    $alignments = ['left', 'center', 'right', 'justify'];
    $spacings = ['single', '1.5', 'double'];
@endphp

<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <form wire:submit="format" class="space-y-6">
            {{-- Upload --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">1. Choose your document</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    A Word file (.docx), up to {{ round(config('docxformatter.max_upload_kb') / 1024) }} MB. Your original is never changed.
                </p>

                <input type="file" wire:model="file" accept=".docx" class="mt-4 block w-full text-sm text-gray-700 dark:text-gray-300">
                <div wire:loading wire:target="file" class="mt-2 text-sm text-gray-500">Uploading...</div>
                <x-input-error for="file" class="mt-2" />
            </div>

            {{-- Presets --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">2. Set the formatting rules</h3>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-secondary-button wire:click="loadExample">Load example</x-secondary-button>

                    @foreach ($this->presets as $preset)
                        <span class="inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 text-sm">
                            <button type="button" wire:click="loadPreset({{ $preset->id }})" class="px-3 py-1 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">{{ $preset->name }}</button>
                            <button type="button" wire:click="deletePreset({{ $preset->id }})" wire:confirm="Delete preset '{{ $preset->name }}'?" class="px-2 py-1 text-red-600 border-s border-gray-300 dark:border-gray-600 hover:bg-red-50 dark:hover:bg-gray-700" aria-label="Delete preset {{ $preset->name }}">&times;</button>
                        </span>
                    @endforeach
                </div>

                {{-- Tabs --}}
                <div class="mt-6 flex gap-4 border-b border-gray-200 dark:border-gray-700">
                    @foreach (['form' => 'Form', 'json' => 'Advanced (JSON)'] as $key => $label)
                        <button type="button" wire:click="setTab('{{ $key }}')" class="-mb-px pb-2 text-sm font-medium border-b-2 {{ $tab === $key ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">{{ $label }}</button>
                    @endforeach
                </div>

                @if ($tab === 'json')
                    <textarea wire:model="specJson" rows="22" spellcheck="false" class="mt-4 block w-full font-mono text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                    <p class="mt-2 text-xs text-gray-500">Only settings the formatter understands are accepted. Leave a setting out to use the formatter's default.</p>
                @else
                    <div class="mt-4 space-y-8">
                        <fieldset>
                            <legend class="font-semibold text-gray-900 dark:text-gray-100">Page</legend>
                            <div class="mt-2 grid grid-cols-2 sm:grid-cols-5 gap-4">
                                <x-spec-field label="Size" model="spec.page.size" type="select" :options="['A4', 'Letter']" />
                                <x-spec-field label="Top margin" model="spec.page.margins.top" placeholder="1in" />
                                <x-spec-field label="Bottom margin" model="spec.page.margins.bottom" placeholder="1in" />
                                <x-spec-field label="Left margin" model="spec.page.margins.left" placeholder="1in" />
                                <x-spec-field label="Right margin" model="spec.page.margins.right" placeholder="1in" />
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Lengths accept in, cm or mm, for example 1in, 2.5cm or 25mm.</p>
                        </fieldset>

                        <fieldset>
                            <legend class="font-semibold text-gray-900 dark:text-gray-100">Font</legend>
                            <div class="mt-2 grid grid-cols-2 gap-4">
                                <x-spec-field label="Family" model="spec.font.family" placeholder="Times New Roman" />
                                <x-spec-field label="Size (pt)" model="spec.font.size_pt" type="number" />
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="font-semibold text-gray-900 dark:text-gray-100">Paragraphs</legend>
                            <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <x-spec-field label="Line spacing" model="spec.paragraph.line_spacing" type="select" :options="$spacings" />
                                <x-spec-field label="Space before (pt)" model="spec.paragraph.space_before_pt" type="number" />
                                <x-spec-field label="Space after (pt)" model="spec.paragraph.space_after_pt" type="number" />
                                <x-spec-field label="Alignment" model="spec.paragraph.alignment" type="select" :options="$alignments" />
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="font-semibold text-gray-900 dark:text-gray-100">Headings</legend>
                            <div class="mt-2 grid grid-cols-2 sm:grid-cols-5 gap-4">
                                <x-spec-field label="Font family" model="spec.headings.font_family" />
                                @foreach (['Title', 'Heading 1', 'Heading 2', 'Heading 3'] as $style)
                                    <x-spec-field :label="$style.' size (pt)'" :model="'spec.headings.size_overrides_pt.'.$style" type="number" />
                                @endforeach
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-700 dark:text-gray-300">
                                <span>Bold:</span>
                                @foreach (['Title', 'Heading 1', 'Heading 2', 'Heading 3'] as $style)
                                    <label class="flex items-center gap-1">
                                        <input type="checkbox" wire:model="spec.headings.bold" value="{{ $style }}" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ $style }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="font-semibold text-gray-900 dark:text-gray-100">Page numbers</legend>
                            <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-4 items-end">
                                <x-spec-field label="Show page numbers" model="spec.page_numbers.enabled" type="checkbox" />
                                <x-spec-field label="Position" model="spec.page_numbers.position" type="select" :options="['header', 'footer']" />
                                <x-spec-field label="Alignment" model="spec.page_numbers.alignment" type="select" :options="['left', 'center', 'right']" />
                                <x-spec-field label="Start at" model="spec.page_numbers.start_at" type="number" />
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="font-semibold text-gray-900 dark:text-gray-100">Images</legend>
                            <div class="mt-2 grid grid-cols-2 gap-4 items-end">
                                <x-spec-field label="Max width" model="spec.images.max_width" placeholder="page, or 6in" />
                                <x-spec-field label="Centre images" model="spec.images.center" type="checkbox" />
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="font-semibold text-gray-900 dark:text-gray-100">Tables</legend>
                            <div class="mt-2 grid grid-cols-2 sm:grid-cols-5 gap-4 items-end">
                                <x-spec-field label="Auto-fit" model="spec.tables.auto_fit" type="checkbox" />
                                <x-spec-field label="Width" model="spec.tables.preferred_width" placeholder="page, or 6in" />
                                <x-spec-field label="Alignment" model="spec.tables.alignment" type="select" :options="['left', 'center', 'right']" />
                                <x-spec-field label="Font family" model="spec.tables.font_family" />
                                <x-spec-field label="Size (pt)" model="spec.tables.size_pt" type="number" />
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="font-semibold text-gray-900 dark:text-gray-100">References</legend>
                            <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-4 items-end">
                                <x-spec-field label="Format the references list" model="spec.references.enabled" type="checkbox" />
                                <x-spec-field label="Number the entries" model="spec.references.apply_numbering_style" type="checkbox" />
                                <x-spec-field label="Stop at next heading" model="spec.references.stop_at_next_heading" type="checkbox" />
                                <x-spec-field label="Numbering style" model="spec.references.numbering_style" placeholder="List Number" />
                                <x-spec-field label="Hanging indent" model="spec.references.hanging_indent" placeholder="0.5in" />
                                <x-spec-field label="Line spacing" model="spec.references.line_spacing" type="select" :options="$spacings" />
                                <x-spec-field label="Alignment" model="spec.references.alignment" type="select" :options="$alignments" />
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="font-semibold text-gray-900 dark:text-gray-100">Clean-up</legend>
                            <div class="mt-2 flex flex-wrap gap-6">
                                <x-spec-field label="Remove empty paragraphs" model="spec.cleanup.remove_empty_paragraphs" type="checkbox" />
                                <x-spec-field label="Collapse repeated blank lines" model="spec.cleanup.collapse_multiple_newlines" type="checkbox" />
                            </div>
                        </fieldset>

                        <p class="text-xs text-gray-500">More settings, such as which Word styles get the font, are in the Advanced (JSON) tab.</p>
                    </div>
                @endif

                {{-- Spec errors --}}
                @php
                    $specErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'spec'))->flatten();
                @endphp
                @if ($specErrors->isNotEmpty())
                    <ul class="mt-4 list-disc list-inside text-sm text-red-600 dark:text-red-400">
                        @foreach ($specErrors as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                @endif

                {{-- Save preset --}}
                <div class="mt-6 flex flex-wrap items-start gap-2">
                    <div>
                        <input type="text" wire:model="presetName" wire:keydown.enter.prevent="savePreset" placeholder="Preset name, e.g. Journal X" class="block w-64 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <x-input-error for="presetName" class="mt-1" />
                    </div>
                    <x-secondary-button wire:click="savePreset">Save as preset</x-secondary-button>
                </div>
            </div>

            {{-- Submit --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">3. Format</h3>

                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model="forceRuns" class="mt-0.5 rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span>Overwrite manual font changes inside the text. Turn this on if the font or size does not change in places where you had formatted text by hand.</span>
                </label>

                <x-button wire:loading.attr="disabled" wire:target="format,file">
                    <span wire:loading.remove wire:target="format">Format document</span>
                    <span wire:loading wire:target="format">Queuing...</span>
                </x-button>
            </div>
        </form>
    </div>
</div>
