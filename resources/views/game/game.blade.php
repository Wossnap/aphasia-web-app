@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="text-center"><span id="level">{{ $level->name }}</span></h2>

    <div class="letter-group text-center">
        @foreach ($letters as $index => $letter)
            <span class="letter
                {{ $index == $currentLetterIndex ? 'underline' : '' }}"
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
        recognition.lang = "sw-TZ"; // Set appropriate language

        let letters = @json($letters->pluck('letter')->toArray());
        let currentLetterIndex = {{ $currentLetterIndex }};
        let expectedLetter = letters[currentLetterIndex];
        let allLetters = @json($letters);

        console.log('all letters'); console.log(allLetters)

        recognition.onresult = function(event) {


            if(document.querySelector(".said-word")){
                document.querySelector(".said-word").classList.remove("said-word");
            }


            // let fullSpokenPhrase = "";
            // let spokenWord = "";

            // for (let i = event.resultIndex; i < event.results.length; i++) {
            //     let transcript = event.results[i][0].transcript.trim().toLowerCase();
            //     fullSpokenPhrase += transcript + " ";

            //     // Update last spoken word separately
            //     let words = transcript.split(" ");
            //     spokenWord = words[words.length - 1]; // Get the last word spoken
            // }

            // console.log("Full spoken phrase:", fullSpokenPhrase);
            // console.log("Last spoken word:", spokenWord);


            let fullSpokenWord = event.results[0][0].transcript.trim().toLowerCase();
            let words = fullSpokenWord.split(" ");
            spokenWord = words[0];//get the first spocken word
            document.getElementById("spoken-letter").innerText = spokenWord;

            console.log('spoken ' + spokenWord);
            let expectedTransliterations = getTransliterationsByIndex(currentLetterIndex);
            console.log(currentLetterIndex);
            console.log('expected transliterations:');
            console.log(expectedTransliterations)
            if (expectedTransliterations.includes(spokenWord)) {
                console.log('right');

                // document.querySelector(".incorrect").classList.remove("incorrect");
                document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("correct");
                document.querySelector(`[data-letter='${expectedLetter}']`).classList.remove("incorrect");
                document.querySelector(`[data-letter='${expectedLetter}']`).classList.remove("underline");

                // Move to the next letter after the current one is correct
                currentLetterIndex++;
                if (currentLetterIndex <= letters.length) {
                    expectedLetter = letters[currentLetterIndex]; // Update to the next letter
                    document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("underline");
                } else {
                    // Handle when all letters are completed
                    document.getElementById("spoken-letter").innerText = "Well done!";
                }
            } else {
                console.log('wrong');

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

        function getTransliterationsByIndex(index) {
            if (index >= 0 && index < allLetters.length) {
                return allLetters[index].transliterations;
            }
            return []; // Return an empty array if index is out of bounds
        }

        startListening();

        setInterval(startListening, 4000); // Restart listening automatically
    });

</script>
@endsection
