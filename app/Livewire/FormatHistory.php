<?php

namespace App\Livewire;

use App\Enums\FormatJobStatus;
use App\Models\FormatJob;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class FormatHistory extends Component
{
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, FormatJob>
     */
    #[Computed]
    public function jobs(): LengthAwarePaginator
    {
        return FormatJob::where('user_id', auth()->id())->latest('id')->paginate(10);
    }

    public function delete(int $id): void
    {
        $formatJob = FormatJob::findOrFail($id);

        Gate::authorize('delete', $formatJob);

        Storage::disk(config('docxformatter.disk'))->deleteDirectory(dirname($formatJob->input_path));

        $formatJob->delete();
    }

    public function render(): View
    {
        $isBusy = $this->jobs->contains(
            fn (FormatJob $job) => in_array($job->status, [FormatJobStatus::Pending, FormatJobStatus::Processing], true),
        );

        return view('livewire.format-history', ['isBusy' => $isBusy]);
    }
}
