<?php

use Vinkla\Hashids\Facades\Hashids;

if (!function_exists('hashid_encode')) {
    function hashid_encode($id)
    {
        return Hashids::encode($id);
    }
}

if (!function_exists('hashid_decode')) {
    function hashid_decode($hash)
    {
        $decoded = Hashids::decode($hash);
        return $decoded[0] ?? null;
    }
}
