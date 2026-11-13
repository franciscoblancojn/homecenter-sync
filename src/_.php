<?php

// Orden fijo: data → services → hooks → api → page. Cada carpeta tiene su _.php.
require_once HCS_DIR . 'src/data/_.php';
require_once HCS_DIR . 'src/services/_.php';
require_once HCS_DIR . 'src/hooks/_.php';
require_once HCS_DIR . 'src/api/_.php';
require_once HCS_DIR . 'src/page/_.php';
