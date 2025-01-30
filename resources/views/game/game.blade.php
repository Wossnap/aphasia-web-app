@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="text-center"><span id="level">{{ $level->name }}</span></h2>

    <div class="letter-group text-center">
        @foreach ($letters as $index => $letter)
            <span class="letter
                {{ $index == $currentLetterIndex -1 ? 'underline' : '' }}"
                data-letter="{{ $letter->letter }}">
                {{ $letter->letter }}
            </span>
        @endforeach
    </div>

    <p class="text-center">
        <strong>Spoken Letter:</strong> <span id="spoken-letter">...</span>
    </p>

    <div class="buttons text-center">
        <button id="next-group" class="btn btn-primary">Next Group</button>
        <button id="next-level" class="btn btn-secondary">Next Level</button>
    </div>
</div>

<style>
    .container {
        max-width: 600px;
        margin: 20px auto;
    }
    .letter-group {
        display: flex;
        justify-content: center;
        gap: 10px;
        font-size: 2em;
        margin-bottom: 20px;
    }
    .letter {
        padding: 5px 10px;
        border-bottom: 3px solid transparent;
    }
    .underline {
        border-bottom: 3px solid blue;
    }
    .incorrect {
        color: red;
        border-bottom: 3px solid red;
    }
    .said-word {
        color: blue;
    }
    .correct {
        color: green;
    }
    .buttons {
        margin-top: 20px;
    }
</style>

<script>
    // document.addEventListener("DOMContentLoaded", function () {
    //     let recognition = new webkitSpeechRecognition();
    //     recognition.continuous = false;
    //     recognition.interimResults = false;
    //     recognition.lang = "sw-KE"; // Set appropriate language

    //     // let currentLetterIndex = {{ $currentLetterIndex }};
    //     let letters = @json($letters->pluck('letter')->toArray());
    //     let expectedLetter = "{{$letters[$currentLetterIndex]->letter}}";

    //     recognition.onresult = function(event) {
    //         let spokenWord = event.results[0][0].transcript.trim().toLowerCase();
    //         document.getElementById("spoken-letter").innerText = spokenWord;

    //         let expectedTransliterations = getTransliterations();

    //         if (expectedTransliterations.includes(spokenWord)) {
    //             document.querySelector(".underline").classList.remove("underline");
    //             document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("correct");
    //         } else {
    //             document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("incorrect");
    //              // Highlight the letter that matches what was actually spoken
    //             let spokenMatch = @json($letters).find(letter => letter.transliterations.includes(spokenWord));
    //             if (spokenMatch) {
    //                 document.querySelector(`[data-letter='${spokenMatch.letter}']`).classList.add("said-word");
    //             }
    //         }
    //     };

    //     recognition.onerror = function(event) {
    //         console.log("Speech recognition error", event);
    //     };

    //     function getTransliterations() {
    //         return @json($letters[$currentLetterIndex]->transliterations);
    //     }

    //     function startListening() {
    //         recognition.start();
    //     }

    //     setInterval(startListening, 3000); // Restart listening automatically
    // });

    document.addEventListener("DOMContentLoaded", function () {
        let recognition = new webkitSpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = "sw-KE"; // Set appropriate language

        let letters = @json($letters->pluck('letter')->toArray());
        let currentLetterIndex = {{ $currentLetterIndex }};
        let expectedLetter = letters[currentLetterIndex - 1]; // Adjust for 0-based index

        recognition.onresult = function(event) {
            let spokenWord = event.results[0][0].transcript.trim().toLowerCase();
            document.getElementById("spoken-letter").innerText = spokenWord;

            let expectedTransliterations = @json($letters[$currentLetterIndex - 1]->transliterations);

            if (expectedTransliterations.includes(spokenWord)) {
                document.querySelector(".underline").classList.remove("underline");
                document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("correct");

                // Move to the next letter after the current one is correct
                currentLetterIndex++;
                if (currentLetterIndex <= letters.length) {
                    expectedLetter = letters[currentLetterIndex - 1]; // Update to the next letter
                    document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("underline");
                } else {
                    // Handle when all letters are completed
                    document.getElementById("spoken-letter").innerText = "Well done!";
                }
            } else {
                document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("incorrect");
                // Highlight the letter that matches what was actually spoken
                let spokenMatch = @json($letters).find(letter => letter.transliterations.includes(spokenWord));
                if (spokenMatch) {
                    document.querySelector(`[data-letter='${spokenMatch.letter}']`).classList.add("said-word");
                }
            }
        };

        recognition.onerror = function(event) {
            console.log("Speech recognition error", event);
        };

        function startListening() {
            recognition.start();
        }

        setInterval(startListening, 3000); // Restart listening automatically
    });

</script>
@endsection
