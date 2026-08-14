@extends('admin.layout')

@section('title', 'Edit Category')
@section('header', 'Edit Category: ' . $category->name)

@section('content')
    <div class="max-w-2xl mx-auto">
        <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Category Name *</label>
                                                                                    <input type="text" name="name" id="name" required
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   style="border: 2px solid #9ca3af !important;"
                                   value="{{ old('name', $category->name) }}">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea name="description" id="description" rows="3"
                                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                      style="border: 2px solid #9ca3af !important;">{{ old('description', $category->description) }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="session_mode" class="block text-sm font-medium text-gray-700">
                                Guided session works by
                            </label>
                            <select name="session_mode" id="session_mode"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    style="border: 2px solid #9ca3af !important;">
                                @foreach(\App\Models\Category::sessionModes() as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ old('session_mode', $category->session_mode) === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">
                                Level by level suits the fidel category, where a level is one consonant family, so he
                                works ሀ ሁ ሂ ሃ … together. It also suits a category whose levels are difficulty tiers.
                                Either way a miss still moves him out of the level — staying would put him straight
                                back into the sound he just missed.
                            </p>
                            @error('session_mode')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="easy_level_mode" class="block text-sm font-medium text-gray-700">
                                Easy levels <span class="text-gray-400 font-normal">(only used when working level by level)</span>
                            </label>
                            <select name="easy_level_mode" id="easy_level_mode"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    style="border: 2px solid #9ca3af !important;">
                                @foreach(\App\Models\Category::easyLevelModes() as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ old('easy_level_mode', $category->easy_level_mode) === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">
                                Hard and middling levels are always walked as whole rows. This only changes what happens
                                to the levels he is already good at: either they are walked as rows too, or their
                                letters are spread through the session as short runs of wins.
                                <br>
                                <span class="text-gray-400">
                                    There is no settled answer. The one aphasia study to compare the two found them
                                    equal for learning something, and mixing slightly better for still having it three
                                    months later — in 4 of 10 people, by an amount the authors called modest. Leave it
                                    on whole rows unless you want to try the other and compare his own numbers.
                                </span>
                            </p>
                            @error('easy_level_mode')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.categories.index') }}"
                   class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                    Cancel
                </a>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Update Category
                </button>
            </div>
        </form>
    </div>
@endsection
