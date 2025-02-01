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

        .none-said {
            color: red !important;
        }

        .said-word {
            color: blue !important;
        }

        .correct {
            color: green;
        }

        .buttons {
            margin-top: 20px;
        }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let recognition = new webkitSpeechRecognition();
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.lang = "am-ET"; // Set appropriate language

            let letters = @json($letters->pluck('letter')->toArray());
            let currentLetterIndex = {{ $currentLetterIndex }};
            let expectedLetter = letters[currentLetterIndex];
            let allLetters = @json($letters);

            console.log('all letters');
            console.log(allLetters)

            recognition.onresult = function(event) {


                if (document.querySelector(".said-word")) {
                    document.querySelector(".said-word").classList.remove("said-word");
                }

                document.querySelectorAll(".letter").forEach(el => el.classList.remove("none-said"));


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
                spokenWord = words[0]; //get the first spocken word
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
                    console.log('index ' +currentLetterIndex);
                    if (currentLetterIndex < letters.length) {
                        expectedLetter = letters[currentLetterIndex]; // Update to the next letter
                        document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("underline");
                    } else {
                        currentLetterIndex = 0;
                        expectedLetter = letters[currentLetterIndex];

                        // Handle when all letters are completed
                        //  document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("correct");
                         document.querySelectorAll(".letter").forEach(el => el.classList.remove("correct"));

                        document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("underline");
                        document.getElementById("spoken-letter").innerText = "Well done!!!!!!";
                    }
                } else {
                    console.log('wrong');

                    document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("incorrect");
                    // Highlight the letter that matches what was actually spoken
                    // spokenTransliterations = getTransliterationsByLetter(spokenWord)
                    // spokenLetter = getLetterByTransliteration(spokenWord)
                    // console.log('spoken letter ' + spokenLetter)
                    // // let spokenMatch = @json($letters).find(letter => letter.transliterations.includes(spokenWord));
                    // if (spokenLetter) {
                    //     document.querySelector(`[data-letter='${spokenLetter}']`).classList.add("said-word");
                    // }

                    let spokenIndex = getIndexByTransliteration(spokenWord, allLetters);
                    console.log('spoken index: ', spokenIndex);

                    if (spokenIndex !== -1) {
                        console.log('in here')
                        document.querySelectorAll(".letter")[spokenIndex].classList.add("said-word");
                        document.querySelectorAll(".letter")[spokenIndex].classList.remove("underline");
                    } else {
                        document.querySelectorAll(".letter").forEach(el => el.classList.add("none-said"));
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

            function getTransliterationsByLetter(letter) {
                let letterObj = allLetters.find(item => item.letter === letter);
                return letterObj ? letterObj.transliterations : [];
            }

            function getLetterByTransliteration(transliteration) {
                let letterObj = allLetters.find(item => item.transliterations.includes(transliteration));
                return letterObj ? letterObj.letter : null;
            }

            function getIndexByTransliteration(transliteration) {
                return allLetters.findIndex(item => item.transliterations.includes(transliteration));
            }

            recognition.onend = function() {
                // Restart recognition after a small delay
                setTimeout(() => {
                    recognition.start();
                }, 100); // 100ms delay before restarting
            };

            recognition.start();
        });


        // document.addEventListener("DOMContentLoaded", function () {
        //     let recognition = new webkitSpeechRecognition();
        //     recognition.continuous = false;
        //     recognition.interimResults = false;
        //     recognition.lang = "am-ET"; // Set appropriate language

        //     // let currentLetterIndex = {{ $currentLetterIndex }};
        //     let letters = @json($letters->pluck('letter')->toArray());
        //     let expectedLetter = "{{ $letters[$currentLetterIndex]->letter }}";

        //     recognition.onresult = function(event) {
        //         let spokenWord = event.results[0][0].transcript.trim().toLowerCase();
        //         document.getElementById("spoken-letter").innerText = spokenWord;
        //         console.log('spoken word ' + spokenWord);
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

        //     recognition.onend = function() {
        //         // Restart recognition after a small delay
        //         setTimeout(() => {
        //             recognition.start();
        //         }, 100); // 100ms delay before restarting
        //     };

        //     recognition.start();
        // });

        // document.addEventListener("DOMContentLoaded", function() {
        //     let recognition = new webkitSpeechRecognition();
        //     recognition.continuous = true;
        //     recognition.interimResults = false;
        //     recognition.lang = "am-ET"; // Set appropriate language

        //     let letters = @json($letters->pluck('letter')->toArray());
        //     let currentLetterIndex = {{ $currentLetterIndex }};
        //     let expectedLetter = letters[currentLetterIndex];
        //     let allLetters = @json($letters);

        //     console.log('all letters');
        //     console.log(allLetters)

        //     // recognition.onresult = function(event) {


        //     //     if(document.querySelector(".said-word")){
        //     //         document.querySelector(".said-word").classList.remove("said-word");
        //     //     }

        //     //     document.querySelectorAll(".letter").forEach(el => el.classList.remove("none-said"));


        //     //     // let fullSpokenPhrase = "";
        //     //     // let spokenWord = "";

        //     //     // for (let i = event.resultIndex; i < event.results.length; i++) {
        //     //     //     let transcript = event.results[i][0].transcript.trim().toLowerCase();
        //     //     //     fullSpokenPhrase += transcript + " ";

        //     //     //     // Update last spoken word separately
        //     //     //     let words = transcript.split(" ");
        //     //     //     spokenWord = words[words.length - 1]; // Get the last word spoken
        //     //     // }

        //     //     // console.log("Full spoken phrase:", fullSpokenPhrase);
        //     //     // console.log("Last spoken word:", spokenWord);


        //     //     let fullSpokenWord = event.results[0][0].transcript.trim().toLowerCase();
        //     //     let words = fullSpokenWord.split(" ");
        //     //     spokenWord = words[0];//get the first spocken word
        //     //     document.getElementById("spoken-letter").innerText = spokenWord;

        //     //     console.log('spoken ' + spokenWord);
        //     //     let expectedTransliterations = getTransliterationsByIndex(currentLetterIndex);
        //     //     console.log(currentLetterIndex);
        //     //     console.log('expected transliterations:');
        //     //     console.log(expectedTransliterations)
        //     //     if (expectedTransliterations.includes(spokenWord)) {
        //     //         console.log('right');

        //     //         // document.querySelector(".incorrect").classList.remove("incorrect");
        //     //         document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("correct");
        //     //         document.querySelector(`[data-letter='${expectedLetter}']`).classList.remove("incorrect");
        //     //         document.querySelector(`[data-letter='${expectedLetter}']`).classList.remove("underline");

        //     //         // Move to the next letter after the current one is correct
        //     //         currentLetterIndex++;
        //     //         if (currentLetterIndex <= letters.length) {
        //     //             expectedLetter = letters[currentLetterIndex]; // Update to the next letter
        //     //             document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("underline");
        //     //         } else {
        //     //             // Handle when all letters are completed
        //     //             document.getElementById("spoken-letter").innerText = "Well done!";
        //     //         }
        //     //     } else {
        //     //         console.log('wrong');

        //     //         document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("incorrect");
        //     //         // Highlight the letter that matches what was actually spoken
        //     //         // spokenTransliterations = getTransliterationsByLetter(spokenWord)
        //     //         // spokenLetter = getLetterByTransliteration(spokenWord)
        //     //         // console.log('spoken letter ' + spokenLetter)
        //     //         // // let spokenMatch = @json($letters).find(letter => letter.transliterations.includes(spokenWord));
        //     //         // if (spokenLetter) {
        //     //         //     document.querySelector(`[data-letter='${spokenLetter}']`).classList.add("said-word");
        //     //         // }

        //     //         let spokenIndex = getIndexByTransliteration(spokenWord, allLetters);
        //     //         console.log('spoken index: ', spokenIndex);

        //     //         if (spokenIndex !== -1) {
        //     //             console.log('in here')
        //     //             document.querySelectorAll(".letter")[spokenIndex].classList.add("said-word");
        //     //             document.querySelectorAll(".letter")[spokenIndex].classList.remove("underline");
        //     //         }else{
        //     //             document.querySelectorAll(".letter").forEach(el => el.classList.add("none-said"));
        //     //         }


        //     //     }
        //     // };

        //     recognition.onresult = function(event) {
        //         let spokenWord = "";

        //         // Get the most confident result
        //         for (let i = event.resultIndex; i < event.results.length; i++) {
        //             console.log(event.results[i]);
        //             if (event.results[i].isFinal) {
        //                 spokenWord = event.results[i][0].transcript.trim().toLowerCase().split(" ").pop();

        //                 break;
        //             }
        //         }

        //         console.log('soken word ' + spokenWord);

        //         if (!spokenWord) return; // Skip if no final result

        //         document.getElementById("spoken-letter").innerText = spokenWord;

        //         let expectedTransliterations = getTransliterationsByIndex(currentLetterIndex);

        //         if (expectedTransliterations.includes(spokenWord)) {
        //             document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("correct");
        //             document.querySelector(`[data-letter='${expectedLetter}']`).classList.remove("incorrect",
        //                 "underline");

        //             // Move to the next letter
        //             currentLetterIndex++;
        //             if (currentLetterIndex < letters.length) {
        //                 expectedLetter = letters[currentLetterIndex];
        //                 document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("underline");
        //             } else {
        //                 document.getElementById("spoken-letter").innerText = "Well done!";
        //             }
        //         } else {
        //             document.querySelector(`[data-letter='${expectedLetter}']`).classList.add("incorrect");

        //             let spokenIndex = getIndexByTransliteration(spokenWord, allLetters);
        //             if (spokenIndex !== -1) {
        //                 document.querySelectorAll(".letter")[spokenIndex].classList.add("said-word");
        //                 document.querySelectorAll(".letter")[spokenIndex].classList.remove("underline");
        //             } else {
        //                 document.querySelectorAll(".letter").forEach(el => el.classList.add("none-said"));
        //             }
        //         }
        //     };


        //     recognition.onerror = function(event) {
        //         console.log("Speech recognition error", event);
        //     };


        //     recognition.onend = function() {
        //         // Automatically restart recognition if it stops unexpectedly
        //         recognition.start();
        //     };
        //     // function startListening() {
        //     //     recognition.start();
        //     // }

        //     function getTransliterationsByIndex(index) {
        //         if (index >= 0 && index < allLetters.length) {
        //             return allLetters[index].transliterations;
        //         }
        //         return []; // Return an empty array if index is out of bounds
        //     }

        //     function getTransliterationsByLetter(letter) {
        //         let letterObj = allLetters.find(item => item.letter === letter);
        //         return letterObj ? letterObj.transliterations : [];
        //     }

        //     function getLetterByTransliteration(transliteration) {
        //         let letterObj = allLetters.find(item => item.transliterations.includes(transliteration));
        //         return letterObj ? letterObj.letter : null;
        //     }

        //     function getIndexByTransliteration(transliteration) {
        //         return allLetters.findIndex(item => item.transliterations.includes(transliteration));
        //     }


        //     // startListening();
        //     recognition.start();
        //     // setInterval(startListening, 3000); // Restart listening automatically
        // });
    </script>
@endsection
