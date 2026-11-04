<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Format a document') }}
        </h2>
    </x-slot>

    <livewire:format-document />
</x-app-layout>
