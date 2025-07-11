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
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   style="border: 1px solid #d1d5db !important;"
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
                            <label for="transliterations-input" class="block text-sm font-medium text-gray-700">Transliterations</label>
                                                        <div class="mt-1">
                                <div id="transliterations-container" class="min-h-[40px] border border-gray-300 rounded-md p-2 flex flex-wrap gap-2 focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500 bg-white">
                                    <!-- Pills will appear here -->
                                </div>
                                <input type="text" id="transliterations-input"
                                       class="mt-2 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Type a transliteration and press Enter or Tab">
                                <input type="hidden" name="transliterations" id="transliterations-hidden"
                                       value="{{ old('transliterations', is_array($word->transliterations) ? implode(',', $word->transliterations) : '') }}">
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Type different pronunciations and press Enter or Tab to add them as pills</p>
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

                            <!-- Voice Recording Section -->
                            <div class="mb-4 p-3 bg-gray-50 rounded-md">
                                <div class="flex items-center space-x-2 mb-2">
                                    <button type="button" id="record-btn" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                                        🎤 Record
                                    </button>
                                    <button type="button" id="stop-btn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm" disabled>
                                        ⏹️ Stop
                                    </button>
                                    <button type="button" id="play-btn" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm" disabled>
                                        ▶️ Play
                                    </button>
                                    <button type="button" id="clear-btn" class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-1 rounded text-sm" disabled>
                                        🗑️ Clear
                                    </button>
                                </div>
                                <div id="recording-status" class="text-sm text-gray-600"></div>
                                <audio id="recorded-audio" controls class="w-full mt-2" style="display: none;"></audio>
                                <input type="hidden" id="recorded-audio-data" name="recorded_audio_data">
                            </div>

                            <!-- File Upload Section -->
                            <div>
                                <input type="file" name="audio_file" id="audio_file" accept=".mp3,.wav,.ogg"
                                       class="mt-1 block w-full">
                                <p class="mt-1 text-sm text-gray-500">MP3, WAV, or OGG (max 10MB) OR use recording above</p>
                            </div>
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

@push('scripts')
<script>
// ===== TRANSLITERATIONS PILLS FUNCTIONALITY =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('Transliterations script loaded');
    const input = document.getElementById('transliterations-input');
    const container = document.getElementById('transliterations-container');
    const hiddenInput = document.getElementById('transliterations-hidden');
    let pills = [];

    if (!input || !container || !hiddenInput) {
        console.error('Required elements not found for transliterations');
        return;
    }

    // Load existing pills from hidden input
    const existingValues = hiddenInput.value;
    if (existingValues) {
        existingValues.split(',').forEach(value => {
            const trimmed = value.trim();
            if (trimmed) {
                pills.push(trimmed);
                container.appendChild(createPill(trimmed));
            }
        });
    }

    function updateHiddenInput() {
        hiddenInput.value = pills.join(',');
    }

        function createPill(text) {
        const pill = document.createElement('span');
        pill.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200';
        pill.innerHTML = `
            <span class="mr-2">${text}</span>
            <button type="button" class="ml-1 text-blue-600 hover:text-red-600 font-bold text-lg leading-none remove-pill-btn" style="background: none; border: none; cursor: pointer;">&times;</button>
        `;

        // Add event listener to remove button
        const removeBtn = pill.querySelector('.remove-pill-btn');
        removeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            removePill(pill, text);
        });

        return pill;
    }

    function addPill(text) {
        text = text.trim();
        if (text && !pills.includes(text)) {
            pills.push(text);
            container.appendChild(createPill(text));
            updateHiddenInput();
        }
    }

    function removePill(pillElement, text) {
        pills = pills.filter(pill => pill !== text);
        pillElement.remove();
        updateHiddenInput();
    }

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            addPill(input.value);
            input.value = '';
        }
    });

    input.addEventListener('blur', function() {
        if (input.value.trim()) {
            addPill(input.value);
            input.value = '';
        }
    });
});

// ===== VOICE RECORDING FUNCTIONALITY =====
let mediaRecorder;
let audioChunks = [];
let recordedBlob;

document.addEventListener('DOMContentLoaded', function() {
    const recordBtn = document.getElementById('record-btn');
    const stopBtn = document.getElementById('stop-btn');
    const playBtn = document.getElementById('play-btn');
    const clearBtn = document.getElementById('clear-btn');
    const status = document.getElementById('recording-status');
    const audioElement = document.getElementById('recorded-audio');
    const hiddenAudioData = document.getElementById('recorded-audio-data');

    recordBtn.addEventListener('click', startRecording);
    stopBtn.addEventListener('click', stopRecording);
    playBtn.addEventListener('click', playRecording);
    clearBtn.addEventListener('click', clearRecording);

    async function startRecording() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];

            mediaRecorder.ondataavailable = event => {
                audioChunks.push(event.data);
            };

                        mediaRecorder.onstop = () => {
                recordedBlob = new Blob(audioChunks, { type: 'audio/wav' });
                const audioUrl = URL.createObjectURL(recordedBlob);
                audioElement.src = audioUrl;
                audioElement.style.display = 'block';
                playBtn.disabled = false;
                clearBtn.disabled = false;

                // Convert blob to base64 for form submission
                const reader = new FileReader();
                reader.onloadend = () => {
                    hiddenAudioData.value = reader.result;
                };
                reader.readAsDataURL(recordedBlob);
            };

            mediaRecorder.start();
            recordBtn.disabled = true;
            stopBtn.disabled = false;
            status.textContent = 'Recording... 🔴';
        } catch (err) {
            console.error('Error accessing microphone:', err);
            status.textContent = 'Error: Could not access microphone';
        }
    }

    function stopRecording() {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
        recordBtn.disabled = false;
        stopBtn.disabled = true;
        status.textContent = 'Recording completed ✓';
    }

    function playRecording() {
        audioElement.play();
    }

    function clearRecording() {
        console.log('Clearing recording');
        audioElement.style.display = 'none';
        audioElement.src = '';
        hiddenAudioData.value = '';
        recordedBlob = null;
        audioChunks = [];
        playBtn.disabled = true;
        clearBtn.disabled = true;
        status.textContent = 'Recording cleared. Ready to record again.';
        console.log('Hidden audio data cleared:', hiddenAudioData.value);
    }
});
</script>
@endpush
