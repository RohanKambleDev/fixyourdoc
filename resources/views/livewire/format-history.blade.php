@php
    $statusColours = [
        'pending' => 'bg-gray-100 text-gray-700',
        'processing' => 'bg-blue-100 text-blue-700',
        'done' => 'bg-green-100 text-green-700',
        'failed' => 'bg-red-100 text-red-700',
    ];
@endphp

<div class="py-12" @if ($isBusy) wire:poll.3s @endif>
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden">
            @forelse ($this->jobs as $job)
                <div wire:key="job-{{ $job->id }}" class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-gray-900 dark:text-gray-100">{{ $job->original_name }}</p>
                        <p class="text-xs text-gray-500">{{ $job->created_at->diffForHumans() }}</p>
                        @if ($job->error)
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $job->error }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="px-2 py-1 rounded text-xs font-medium {{ $statusColours[$job->status->value] }}">{{ ucfirst($job->status->value) }}</span>

                        <a href="{{ route('format.download', [$job, 'original']) }}" class="text-sm text-gray-600 dark:text-gray-400 underline">Original</a>

                        @if ($job->status === \App\Enums\FormatJobStatus::Done)
                            <a href="{{ route('format.download', [$job, 'formatted']) }}" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 underline">Download formatted</a>
                        @endif

                        <button type="button" wire:click="delete({{ $job->id }})" wire:confirm="Delete this job and its files?" class="text-sm text-red-600 hover:underline">Delete</button>
                    </div>
                </div>
            @empty
                <p class="px-6 py-10 text-center text-gray-500">
                    Nothing here yet. <a href="{{ route('format.create') }}" class="text-indigo-600 underline">Format a document</a>.
                </p>
            @endforelse
        </div>

        <div class="mt-4">{{ $this->jobs->links() }}</div>
    </div>
</div>
