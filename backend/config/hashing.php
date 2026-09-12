<?php

return [
    // Argon2id per the Savv MVP security requirements.
    'driver' => 'argon2id',

    'argon' => [
        'memory' => 65536,
        'threads' => 1,
        'time' => 4,
        'verify' => true,
    ],
];
