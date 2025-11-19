<?php
// Cambia esta contraseña por la que QUIERAS usar para entrar
$contra = '123';

$hash = password_hash($contra, PASSWORD_DEFAULT);

echo "<p>Contraseña en texto: <strong>{$contra}</strong></p>";
echo "<p>Hash generado:</p>";
echo "<textarea style='width:100%;height:100px;'>" . $hash . "</textarea>";
