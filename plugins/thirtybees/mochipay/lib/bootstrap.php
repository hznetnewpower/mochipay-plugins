<?php
// Self-contained PHP 7.4+ gateway code; no Composer downloads at checkout.
if (!class_exists('MochiPayShared\\Client', false)) {
    require_once __DIR__ . '/Client.php';
    require_once __DIR__ . '/Store.php';
    require_once __DIR__ . '/Payment.php';
    require_once __DIR__ . '/Page.php';
}
