<?php

$object = new class {
    public function var_dump(): void
    {
        // does stuff
    }
};

// bar some code

echo 'it passes !';

$object->var_dump('ok because not the real var_dump');

// foo some more code
