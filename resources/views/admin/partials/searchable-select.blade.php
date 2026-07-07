{{--
    Dependency-free searchable combobox: a text input that filters a click-to-pick
    list, backed by a hidden input for the real form value. Used where a plain
    <select> would have too many options to scan (e.g. hundreds of words).

    Expected variables:
    - name:        form field name for the submitted value
    - options:     array/collection of ['id' => ..., 'label' => ...]
    - selected:    currently selected id (nullable)
    - placeholder: input placeholder text
    - emptyLabel:  label for the "no filter" option
--}}
@php
    $selectedOption = collect($options)->firstWhere('id', $selected);
@endphp
<div class="relative searchable-select">
    <input type="text"
           class="block w-56 border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500"
           placeholder="{{ $placeholder ?? 'Search...' }}"
           autocomplete="off"
           value="{{ $selectedOption['label'] ?? '' }}"
           data-search-input>
    <input type="hidden" name="{{ $name }}" value="{{ $selected }}" data-hidden-input>
    <div class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-auto hidden" data-options-list>
        <div class="px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 text-gray-500" data-value="" data-label="">
            {{ $emptyLabel ?? 'All' }}
        </div>
        @foreach($options as $option)
            <div class="px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 {{ (string) $selected === (string) $option['id'] ? 'bg-blue-50 font-medium' : '' }}"
                 data-value="{{ $option['id'] }}"
                 data-label="{{ $option['label'] }}">
                {{ $option['label'] }}
            </div>
        @endforeach
    </div>
</div>
