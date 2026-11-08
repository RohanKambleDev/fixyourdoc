<?php

use App\Enums\FormatJobStatus;
use App\Jobs\FormatDocumentJob;
use App\Livewire\FormatDocument;
use App\Livewire\FormatHistory;
use App\Models\FormatJob;
use App\Models\FormatPreset;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * A minimal but valid .docx, built without Word or python-docx.
 */
function fakeDocx(string $name = 'paper.docx'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Hello</w:t></w:r></w:p><w:p/><w:p/><w:sectPr/></w:body></w:document>');
    $zip->close();

    return UploadedFile::fake()->createWithContent($name, file_get_contents($path));
}

beforeEach(function () {
    Storage::fake(config('docxformatter.disk'));
    $this->actingAs($this->user = User::factory()->create());
});

test('a docx upload creates a job and queues it', function () {
    Queue::fake();

    Livewire::test(FormatDocument::class)
        ->set('file', fakeDocx())
        ->call('format')
        ->assertRedirect(route('format.history'));

    Queue::assertPushed(FormatDocumentJob::class);
    expect(FormatJob::sole())
        ->user_id->toBe($this->user->id)
        ->original_name->toBe('paper.docx')
        ->status->toBe(FormatJobStatus::Pending);
});

test('formatting jobs go on their own queue', function () {
    Queue::fake();

    Livewire::test(FormatDocument::class)
        ->set('file', fakeDocx())
        ->call('format');

    Queue::assertPushedOn('formatting', FormatDocumentJob::class);
});

test('a file that is not a real docx is rejected', function () {
    Livewire::test(FormatDocument::class)
        ->set('file', UploadedFile::fake()->createWithContent('paper.docx', 'not a zip'))
        ->call('format')
        ->assertHasErrors('file');
});

test('a document is required', function () {
    Livewire::test(FormatDocument::class)
        ->call('format')
        ->assertHasErrors('file');
});

test('form values are stored as numbers, not text', function () {
    Queue::fake();

    Livewire::test(FormatDocument::class)
        ->set('file', fakeDocx())
        ->set('spec.font.size_pt', '14')
        ->set('spec.headings.size_overrides_pt.Heading 3', '10')
        ->call('format');

    expect(FormatJob::sole()->spec)
        ->font->size_pt->toBe(14)
        ->headings->size_overrides_pt->{'Heading 3'}->toBe(10);
});

test('an invalid value is reported and nothing is queued', function () {
    Queue::fake();

    Livewire::test(FormatDocument::class)
        ->set('file', fakeDocx())
        ->set('spec.page.margins.left', '2 parsecs')
        ->call('format')
        ->assertHasErrors('spec.page.margins.left');

    Queue::assertNothingPushed();
});

test('the json tab accepts a spec and rejects unknown settings', function () {
    Queue::fake();

    Livewire::test(FormatDocument::class)
        ->call('setTab', 'json')
        ->set('file', fakeDocx())
        ->set('specJson', '{"evil": {"cmd": "rm -rf /"}}')
        ->call('format')
        ->assertHasErrors('spec.evil.cmd');

    Queue::assertNothingPushed();
});

test('broken json keeps the user on the json tab', function () {
    Livewire::test(FormatDocument::class)
        ->call('setTab', 'json')
        ->set('specJson', '{ nope')
        ->call('setTab', 'form')
        ->assertSet('tab', 'json')
        ->assertHasErrors('spec');
});

test('presets can be saved, loaded and deleted', function () {
    $component = Livewire::test(FormatDocument::class)
        ->set('spec.font.size_pt', '11')
        ->set('presetName', 'Journal X')
        ->call('savePreset')
        ->assertHasNoErrors();

    $preset = FormatPreset::sole();
    expect($preset->spec['font']['size_pt'])->toBe(11);

    $component->call('loadExample')->call('loadPreset', $preset->id)->assertSet('spec.font.size_pt', 11);
    $component->call('deletePreset', $preset->id);

    expect(FormatPreset::count())->toBe(0);
});

test('preset names are unique per user', function () {
    FormatPreset::create(['user_id' => $this->user->id, 'name' => 'Journal X', 'spec' => []]);

    Livewire::test(FormatDocument::class)
        ->set('presetName', 'Journal X')
        ->call('savePreset')
        ->assertHasErrors('presetName');
});

test('other users presets cannot be loaded', function () {
    $preset = FormatPreset::create(['user_id' => User::factory()->create()->id, 'name' => 'Theirs', 'spec' => []]);

    expect(fn () => Livewire::test(FormatDocument::class)->call('loadPreset', $preset->id))
        ->toThrow(ModelNotFoundException::class);
});

test('the history page lists only my jobs', function () {
    $mine = makeJob($this->user, 'mine.docx');
    makeJob(User::factory()->create(), 'theirs.docx');

    Livewire::test(FormatHistory::class)
        ->assertSee('mine.docx')
        ->assertDontSee('theirs.docx');
});

test('deleting a job removes its files', function () {
    $job = makeJob($this->user, 'mine.docx');

    Livewire::test(FormatHistory::class)->call('delete', $job->id);

    expect(FormatJob::count())->toBe(0);
    Storage::disk(config('docxformatter.disk'))->assertMissing($job->input_path);
});

test('another users job cannot be deleted', function () {
    $job = makeJob(User::factory()->create(), 'theirs.docx');

    Livewire::test(FormatHistory::class)->call('delete', $job->id)->assertForbidden();

    expect(FormatJob::count())->toBe(1);
});

test('the owner can download the original and the formatted file', function () {
    $job = makeJob($this->user, 'mine.docx', done: true);

    $this->get(route('format.download', [$job, 'original']))->assertOk()->assertDownload('mine.docx');
    $this->get(route('format.download', [$job, 'formatted']))->assertOk()->assertDownload('formatted-mine.docx');
});

test('another user cannot download my files', function () {
    $job = makeJob($this->user, 'mine.docx', done: true);

    $this->actingAs(User::factory()->create())
        ->get(route('format.download', [$job, 'original']))
        ->assertForbidden();
});

test('a formatted file that does not exist yet is a 404', function () {
    $job = makeJob($this->user, 'mine.docx');

    $this->get(route('format.download', [$job, 'formatted']))->assertNotFound();
});

test('guests cannot reach the pages', function () {
    auth()->logout();

    $this->get(route('format.create'))->assertRedirect(route('login'));
    $this->get(route('format.history'))->assertRedirect(route('login'));
});

test('a real document is formatted end to end', function () {
    if (! is_file(config('docxformatter.python'))) {
        $this->markTestSkipped('The Python environment in scripts/venv is not set up.');
    }

    Livewire::test(FormatDocument::class)
        ->set('file', fakeDocx())
        ->call('format');

    $job = FormatJob::sole();

    expect($job->status)->toBe(FormatJobStatus::Done);
    Storage::disk(config('docxformatter.disk'))->assertExists($job->output_path);
});

function makeJob(User $user, string $name, bool $done = false): FormatJob
{
    $disk = Storage::disk(config('docxformatter.disk'));
    $directory = "format/{$user->id}/".Str::uuid();
    $disk->put("{$directory}/input.docx", 'original');

    if ($done) {
        $disk->put("{$directory}/output.docx", 'formatted');
    }

    return FormatJob::create([
        'user_id' => $user->id,
        'original_name' => $name,
        'input_path' => "{$directory}/input.docx",
        'output_path' => $done ? "{$directory}/output.docx" : null,
        'spec' => [],
        'status' => $done ? FormatJobStatus::Done : FormatJobStatus::Pending,
    ]);
}
