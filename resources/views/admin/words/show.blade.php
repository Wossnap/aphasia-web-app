@extends('admin.layout')

@section('title', 'View Word')
@section('header', 'Word: ' . $word->word)

@section('content')
    <div class="space-y-6">
        <!-- Word Information -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Word Details</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Amharic Word</dt>
                        <dd class="mt-1 text-lg font-medium text-gray-900">{{ $word->word }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">English Meaning</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $word->meaning ?? 'No meaning provided' }}</dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Transliterations</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if($word->transliterations && count($word->transliterations) > 0)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($word->transliterations as $transliteration)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $transliteration }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                No transliterations provided
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Show in Random</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if($word->show_in_random)
                                <span class="text-green-600 font-medium">Yes</span>
                            @else
                                <span class="text-red-600 font-medium">No</span>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $word->created_at->format('M j, Y g:i A') }}</dd>
                    </div>
                </div>
            </div>
        </div>

        <!-- Media Files -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Media Files</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Audio</label>
                        @if($word->audio_path)
                            <div>
                                <audio controls class="w-full">
                                    <source src="{{ asset('audio/' . $word->audio_path) }}" type="audio/mpeg">
                                </audio>
                                <p class="text-sm text-gray-500 mt-1">{{ $word->audio_path }}</p>
                            </div>
                        @else
                            <p class="text-sm text-gray-500">No audio file</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Image</label>
                        @if($word->image_path)
                            <div>
                                <img src="{{ asset('images/' . $word->image_path) }}" alt="Word image" class="h-32 w-auto rounded border">
                                <p class="text-sm text-gray-500 mt-1">{{ $word->image_path }}</p>
                            </div>
                        @else
                            <p class="text-sm text-gray-500">No image file</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">GIF</label>
                        @if($word->gif_path)
                            <div>
                                <img src="{{ asset('gifs/' . $word->gif_path) }}" alt="Word GIF" class="h-32 w-auto rounded border">
                                <p class="text-sm text-gray-500 mt-1">{{ $word->gif_path }}</p>
                            </div>
                        @else
                            <p class="text-sm text-gray-500">No GIF file</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-between">
            <a href="{{ route('admin.words.index') }}"
               class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                Back to Words
            </a>
            <div class="flex space-x-3">
                <a href="{{ route('admin.words.edit', $word) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Edit Word
                </a>
            </div>
        </div>
    </div>
@endsection
