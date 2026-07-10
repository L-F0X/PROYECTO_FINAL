<?php
// texto_helper.php
// Normaliza la primera letra de cada palabra a mayúscula (ej. "juan carlos" ->
// "Juan Carlos"), sin tocar el resto de las letras, para no interferir con
// apellidos con mayúsculas intermedias legítimas si el usuario ya las escribió.
function capitalizar_nombre(string $texto): string {
    $texto = trim($texto);
    if ($texto === '') {
        return $texto;
    }
    // Solo la primera letra de cada palabra pasa a mayúscula; el resto de
    // cada palabra se deja tal cual la escribió el usuario.
    return preg_replace_callback('/(^|\s)(\p{L})/u', function ($m) {
        return $m[1] . mb_strtoupper($m[2], 'UTF-8');
    }, $texto);
}
