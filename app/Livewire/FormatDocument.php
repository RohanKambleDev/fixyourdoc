<?php

namespace App\Livewire;

use App\Jobs\FormatDocumentJob;
use App\Models\FormatJob;
use App\Models\FormatPreset;
use App\Rules\ValidDocx;
use App\Support\FormatSpec;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class FormatDocument extends Component
{
    use WithFileUploads;

    public $file;

    /** @var array<string, mixed> */
    public array $spec = [];

    public string $tab = 'form';

    public string $specJson = '';

    public bool $forceRuns = false;

    public string $presetName = '';

    public function mount(): void
    {
        $this->spec = FormatSpec::example();
    }

    /**
     * @return Collection<int, FormatPreset>
     */
    #[Computed]
    public function presets(): Collection
    {
        return FormatPreset::where('user_id', auth()->id())->orderBy('name')->get(['id', 'name']);
    }

    public function setTab(string $tab): void
    {
        if ($tab === $this->tab || ! in_array($tab, ['form', 'json'], true)) {
            return;
        }

        $this->resetErrorBag();

        if ($tab === 'json') {
            $this->syncJsonFromSpec();
        } else {
            $spec = $this->specFromJson();

            if ($spec === null) {
                return;
            }

            $this->spec = $spec;
        }

        $this->tab = $tab;
    }

    public function loadExample(): void
    {
        $this->resetErrorBag();
        $this->spec = FormatSpec::example();
        $this->syncJsonFromSpec();
    }

    public function savePreset(): void
    {
        $this->validate([
            'presetName' => ['required', 'string', 'max:100', Rule::unique('format_presets', 'name')->where('user_id', auth()->id())],
        ]);

        $spec = $this->validatedSpec();

        if ($spec === null) {
            return;
        }

        FormatPreset::create(['user_id' => auth()->id(), 'name' => $this->presetName, 'spec' => $spec]);

        $this->presetName = '';
        unset($this->presets);
    }

    public function loadPreset(int $id): void
    {
        $this->resetErrorBag();
        $this->spec = FormatPreset::where('user_id', auth()->id())->findOrFail($id)->spec;
        $this->syncJsonFromSpec();
    }

    public function deletePreset(int $id): void
    {
        FormatPreset::where('user_id', auth()->id())->whereKey($id)->delete();

        unset($this->presets);
    }

    public function format(): mixed
    {
        $this->resetErrorBag();

        $this->validate([
            'file' => ['required', 'file', 'extensions:docx', 'max:'.config('docxformatter.max_upload_kb'), new ValidDocx],
        ]);

        $spec = $this->validatedSpec();

        if ($spec === null) {
            return null;
        }

        $limiterKey = 'format-uploads:'.auth()->id();
        $uploadsPerHour = config('docxformatter.uploads_per_hour');

        if (RateLimiter::tooManyAttempts($limiterKey, $uploadsPerHour)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);
            $this->addError('file', "You can format {$uploadsPerHour} documents per hour. Please try again in {$minutes} minutes.");

            return null;
        }

        RateLimiter::hit($limiterKey, 60 * 60);

        $path = $this->file->storeAs(
            'format/'.auth()->id().'/'.Str::uuid(),
            'input.docx',
            config('docxformatter.disk'),
        );

        $formatJob = FormatJob::create([
            'user_id' => auth()->id(),
            'original_name' => Str::of(basename($this->file->getClientOriginalName()))
                ->replaceMatches('/[^\pL\pN .()_-]+/u', '_')
                ->limit(150, ''),
            'input_path' => $path,
            'spec' => $spec,
            'force_runs' => $this->forceRuns,
        ]);

        FormatDocumentJob::dispatch($formatJob)->onQueue('formatting');

        return $this->redirectRoute('format.history');
    }

    public function render(): View
    {
        return view('livewire.format-document');
    }

    private function syncJsonFromSpec(): void
    {
        $this->specJson = json_encode(
            FormatSpec::normalize($this->spec),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function specFromJson(): ?array
    {
        $decoded = json_decode($this->specJson, true);

        if (! is_array($decoded)) {
            $this->addError('spec', 'The JSON is not valid.');

            return null;
        }

        return $decoded;
    }

    /**
     * The spec from whichever tab is active, normalised and validated.
     * Errors are added under "spec.*" and null is returned when it is invalid.
     *
     * @return array<string, mixed>|null
     */
    private function validatedSpec(): ?array
    {
        $spec = $this->tab === 'json' ? $this->specFromJson() : $this->spec;

        if ($spec === null) {
            return null;
        }

        try {
            return FormatSpec::validate(FormatSpec::normalize($spec));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError("spec.{$key}", $messages[0]);
            }

            return null;
        }
    }
}
