<?php
define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/stripe/lib.php');

list($options, $unrecognized) = cli_get_params(
    ['username' => '', 'help' => false],
    ['u' => 'username', 'h' => 'help']
);

if ($options['help'] || empty($options['username'])) {
    echo "Asignar rol student_suscriptor a un usuario manualmente\n\n";
    echo "Uso:\n";
    echo "  php assign_suscriptor_role.php --username=USERNAME\n";
    echo "  php assign_suscriptor_role.php -u USERNAME\n\n";
    echo "Opciones:\n";
    echo "  -u, --username    Username del usuario\n";
    echo "  -h, --help        Mostrar esta ayuda\n\n";
    exit(0);
}

$username = $options['username'];

// Get user
$user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
if (!$user) {
    cli_error("Usuario '$username' no encontrado");
}

echo "\n";
echo "Usuario encontrado:\n";
echo "  ID: {$user->id}\n";
echo "  Username: {$user->username}\n";
echo "  Email: {$user->email}\n";
echo "  Nombre: {$user->firstname} {$user->lastname}\n\n";

// Assign role
echo "Asignando rol student_suscriptor...\n";
$result = local_stripe_assign_suscriptor_role($user->id);

if ($result) {
    echo "✓ Rol asignado exitosamente\n";
    echo "✓ Usuario inscrito en todos los cursos\n\n";
} else {
    cli_error("Error al asignar rol");
}

echo "Hecho.\n\n";
