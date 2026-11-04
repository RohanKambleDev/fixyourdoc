<?php

use App\Actions\Jetstream\DeleteUser;
use App\Models\User;
use App\Services\DocxFormatter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->output = tempnam(sys_get_temp_dir(), 'out');
});

afterEach(function () {
    @unlink($this->output);
});

test('the script is run with the spec in a temporary file that is removed afterwards', function () {
    $captured = [];

    Process::fake(function ($process) use (&$captured) {
        $specPath = $process->command[array_search('--spec', $process->command, true) + 1];
        $captured = ['path' => $specPath, 'json' => file_get_contents($specPath)];

        return Process::result();
    });

    app(DocxFormatter::class)->format('/in.docx', ['font' => ['size_pt' => 12]], $this->output);

    expect(json_decode($captured['json'], true))->toBe(['font' => ['size_pt' => 12]])
        ->and(file_exists($captured['path']))->toBeFalse();
});

test('force runs adds the flag', function (bool $forceRuns) {
    Process::fake();

    app(DocxFormatter::class)->format('/in.docx', [], $this->output, $forceRuns);

    Process::assertRan(fn ($process) => in_array('--force-runs', $process->command, true) === $forceRuns);
})->with([true, false]);

test('arguments are passed as an array, never through a shell', function () {
    Process::fake();

    app(DocxFormatter::class)->format('/in; rm -rf ~.docx', [], $this->output);

    Process::assertRan(fn ($process) => is_array($process->command) && in_array('/in; rm -rf ~.docx', $process->command, true));
});

test('a failing script gives a generic error and logs the details', function () {
    Process::fake(['*' => Process::result(errorOutput: "Traceback\nPackageNotFoundError: /secret/server/path.docx", exitCode: 1)]);
    Log::shouldReceive('warning')->once()->withArgs(fn (string $message, array $context) => str_contains($context['stderr'], '/secret/server/path.docx'));

    expect(fn () => app(DocxFormatter::class)->format('/in.docx', [], $this->output))
        ->toThrow(RuntimeException::class, 'The document could not be formatted.');
});

test('the error shown to the user never contains server paths', function () {
    Process::fake(['*' => Process::result(errorOutput: 'PackageNotFoundError: /secret/server/path.docx', exitCode: 1)]);

    try {
        app(DocxFormatter::class)->format('/in.docx', [], $this->output);
    } catch (RuntimeException $e) {
        expect($e->getMessage())->not->toContain('/secret');
    }
});

test('a script that exits cleanly but writes nothing is an error', function () {
    Process::fake();
    unlink($this->output);

    expect(fn () => app(DocxFormatter::class)->format('/in.docx', [], $this->output))
        ->toThrow(RuntimeException::class, 'produced no file');
});

test('deleting a user removes their formatting files but not other users files', function () {
    Storage::fake(config('docxformatter.disk'));
    $disk = Storage::disk(config('docxformatter.disk'));
    $user = User::factory()->create();
    $other = User::factory()->create();

    $disk->put("format/{$user->id}/a/input.docx", 'x');
    $disk->put("format/{$user->id}/b/output.docx", 'x');
    $disk->put("format/{$other->id}/a/input.docx", 'x');

    app(DeleteUser::class)->delete($user);

    $disk->assertMissing("format/{$user->id}/a/input.docx");
    $disk->assertMissing("format/{$user->id}/b/output.docx");
    $disk->assertExists("format/{$other->id}/a/input.docx");
});
