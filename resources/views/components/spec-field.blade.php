@props(['label', 'model', 'type' => 'text', 'options' => [], 'placeholder' => null])

<div>
    @if ($type === 'checkbox')
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" wire:model="{{ $model }}" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
            {{ $label }}
        </label>
    @else
        <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">{{ $label }}</label>

        @if ($type === 'select')
            <select wire:model="{{ $model }}" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="">(formatter default)</option>
                @foreach ($options as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        @else
            <input type="{{ $type }}" wire:model="{{ $model }}" placeholder="{{ $placeholder }}" @if ($type === 'number') step="any" @endif class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
        @endif
    @endif
</div>
