<?php

/**
 * Acceso a una opción de wp_options (mismo patrón en todos los plugins del autor).
 */
class HCS_USE_DATA_BASE
{
    protected $KEY = '';
    protected $DATA = [];
    // true solo si la opción se lee en todas las páginas del sitio (frontend). Si solo se usa
    // en el admin, en correos o en AJAX, dejar false.
    protected $AUTOLOAD = false;

    public function __construct()
    {
        $this->DATA = get_option($this->KEY, []);
        if (!is_array($this->DATA)) {
            $this->DATA = [];
        }
    }

    protected function save()
    {
        update_option($this->KEY, $this->DATA, $this->AUTOLOAD);
    }

    public function get()
    {
        return $this->DATA;
    }
}
