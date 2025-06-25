@extends('admin.layout')

@section('title', 'Edit Word')
@section('header', 'Edit Word: ' . $word->word)

@section('content')
    <div class="max-w-4xl mx-auto">
        <form method="POST" action="{{ route('admin.words.update', $word) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Word Information -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Word Information</h3>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="word" class="block text-sm font-medium text-gray-700">Amharic Word *</label>
                            <input type="text" name="word" id="word" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   value="{{ old('word', $word->word) }}">
                            @error('word')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="meaning" class="block text-sm font-medium text-gray-700">English Meaning</label>
                            <input type="text" name="meaning" id="meaning"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   value="{{ old('meaning', $word->meaning) }}">
                            @error('meaning')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="transliterations" class="block text-sm font-medium text-gray-700">Transliterations</label>
                            <input type="text" name="transliterations" id="transliterations"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   value="{{ old('transliterations', is_array($word->transliterations) ? implode(', ', $word->transliterations) : '') }}"
                                   placeholder="Separate multiple pronunciations with commas">
                            <p class="mt-1 text-sm text-gray-500">Enter different pronunciations separated by commas</p>
                            @error('transliterations')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <div class="flex items-center">
                                <input type="checkbox" name="show_in_random" id="show_in_random" value="1"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                       {{ old('show_in_random', $word->show_in_random) ? 'checked' : '' }}>
                                <label for="show_in_random" class="ml-2 block text-sm text-gray-900">
                                    Show in random word selection
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Media Files -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Current Media Files</h3>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Current Audio</label>
                            @if($word->audio_path)
                                <div class="mt-1">
                                    <audio controls class="w-full">
                                        <source src="{{ asset('audio/' . $word->audio_path) }}" type="audio/mpeg">
                                    </audio>
                                    <p class="text-sm text-gray-500 mt-1">{{ $word->audio_path }}</p>
                                </div>
                            @else
                                <p class="mt-1 text-sm text-gray-500">No audio file</p>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Current Image</label>
                            @if($word->image_path)
                                <div class="mt-1">
                                    <img src="{{ asset('images/' . $word->image_path) }}" alt="Word image" class="h-20 w-auto rounded">
                                    <p class="text-sm text-gray-500 mt-1">{{ $word->image_path }}</p>
                                </div>
                            @else
                                <p class="mt-1 text-sm text-gray-500">No image file</p>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Current GIF</label>
                            @if($word->gif_path)
                                <div class="mt-1">
                                    <img src="{{ asset('gifs/' . $word->gif_path) }}" alt="Word GIF" class="h-20 w-auto rounded">
                                    <p class="text-sm text-gray-500 mt-1">{{ $word->gif_path }}</p>
                                </div>
                            @else
                                <p class="mt-1 text-sm text-gray-500">No GIF file</p>
                            @endif
                        </div>
                    </div>

                    <h4 class="text-md font-medium text-gray-900 mb-4">Upload New Media Files</h4>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <label for="audio_file" class="block text-sm font-medium text-gray-700">Replace Audio File</label>
                            <input type="file" name="audio_file" id="audio_file" accept=".mp3,.wav,.ogg"
                                   class="mt-1 block w-full">
                            <p class="mt-1 text-sm text-gray-500">MP3, WAV, or OGG (max 10MB)</p>
                        </div>

                        <div>
                            <label for="image_file" class="block text-sm font-medium text-gray-700">Replace Image File</label>
                            <input type="file" name="image_file" id="image_file" accept="image/*"
                                   class="mt-1 block w-full">
                            <p class="mt-1 text-sm text-gray-500">JPG, PNG, etc. (max 5MB)</p>
                        </div>

                        <div>
                            <label for="gif_file" class="block text-sm font-medium text-gray-700">Replace GIF File</label>
                            <input type="file" name="gif_file" id="gif_file" accept=".gif"
                                   class="mt-1 block w-full">
                            <p class="mt-1 text-sm text-gray-500">GIF only (max 10MB)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Categories</h3>

                    <div class="space-y-4">
                        @foreach($word->categories as $index => $category)
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Category</label>
                                    <select name="categories[]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select a category</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}" {{ $cat->id == $category->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Level</label>
                                    <select name="levels[]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        @for($i = 1; $i <= 10; $i++)
                                            <option value="{{ $i }}" {{ $i == $category->pivot->level ? 'selected' : '' }}>Level {{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.words.index') }}"
                   class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                    Cancel
                </a>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Update Word
                </button>
            </div>
        </form>
    </div>
@endsection
