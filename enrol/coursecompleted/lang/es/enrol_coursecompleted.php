<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'enrol_coursecompleted', language 'es'.
 *
 * @package   enrol_coursecompleted
 * @copyright eWallah (www.eWallah.net)
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['aftercourse'] = 'Después de completar el curso: {$a}';
$string['assignrole'] = 'Asignar rol';
$string['cachedef_compcourses'] = 'Caché de inscripción por finalización de curso';
$string['compcourse'] = 'Curso completado';
$string['compcourse_help'] = 'Qué curso debe completarse.';
$string['confirmbulkdeleteenrolment'] = '¿Estás seguro de que deseas eliminar estas inscripciones de usuarios?';
$string['confirmbulkediteenrolment'] = '¿Estás seguro de que deseas modificar estas inscripciones de usuarios?';
$string['coursecompleted:config'] = 'Configurar instancias de inscripción por finalización de curso';
$string['coursecompleted:enrolpast'] = 'Inscribir usuarios que completaron cursos en el pasado';
$string['coursecompleted:manage'] = 'Gestionar usuarios inscritos';
$string['coursecompleted:unenrol'] = 'Dar de baja usuarios del curso';
$string['coursecompleted:unenrolself'] = 'Darse de baja del curso';
$string['customwelcome'] = 'Mensaje de bienvenida personalizado';
$string['customwelcome_help'] = 'Se puede agregar un mensaje de bienvenida personalizado como texto plano o formato automático de Moodle, incluyendo etiquetas HTML y etiquetas multi-idioma.

Se pueden incluir los siguientes marcadores en el mensaje:

* Nombre del curso {$a->coursename}
* Nombre del curso completado {$a->completed}
* Enlace al perfil del usuario {$a->profileurl}
* Correo del usuario {$a->email}
* Nombre completo del usuario {$a->fullname}';
$string['defaultrole'] = 'Rol asignado por defecto';
$string['defaultrole_desc'] = 'Selecciona el rol a asignar a los usuarios cuando se inscriban.';
$string['deleteselectedusers'] = 'Eliminar inscripciones seleccionadas por finalización de curso';
$string['editselectedusers'] = 'Editar inscripciones seleccionadas por finalización de curso';
$string['editusers'] = 'Cambiar inscripciones de usuarios';
$string['enroldate'] = 'Fecha de inscripción';
$string['enroldate_help'] = 'Si se activa, los usuarios serán inscritos automáticamente en un momento específico en el futuro.';
$string['enrolenddate'] = 'Fecha de finalización';
$string['enrolenddate_help'] = 'Si se activa, los usuarios serán inscritos automáticamente solo hasta esta fecha. Todas las finalizaciones de curso después de esta fecha serán ignoradas.';
$string['enrolenddaterror'] = 'La fecha de finalización de inscripción no puede ser anterior a la fecha de inicio.';
$string['enrolperiod'] = 'Duración de la inscripción';
$string['enrolperiod_desc'] = 'Duración predeterminada de la inscripción. Si se establece en cero, la duración será ilimitada por defecto.';
$string['enrolperiod_help'] = 'Tiempo de validez de la inscripción, desde el momento en que el usuario es inscrito. Si se desactiva, la duración será ilimitada.';
$string['enrolstartdate'] = 'Fecha de inicio';
$string['enrolstartdate_help'] = 'Si se activa, los usuarios solo serán inscritos automáticamente a partir de esta fecha. Todas las finalizaciones de curso antes de esta fecha serán ignoradas.';
$string['expiredaction'] = 'Acción al expirar la inscripción';
$string['expiredaction_help'] = 'Selecciona la acción a realizar cuando expire la inscripción de un usuario. Ten en cuenta que algunos datos y configuraciones del usuario se eliminan al darlo de baja.';
$string['group'] = 'Mantener grupo';
$string['group_help'] = 'Intentar agregar usuarios a un grupo con el mismo nombre';
$string['keepgroup'] = 'Configuración predeterminada de mantener grupo';
$string['keepgroup_help'] = 'Intentar agregar usuarios a un grupo con el mismo nombre por defecto';
$string['pluginname'] = 'Inscripción por curso completado';
$string['pluginname_desc'] = 'El plugin de inscripción por curso completado otorga acceso a cursos cuando se finaliza otro curso.';
$string['privacy:metadata'] = 'El plugin de inscripción por curso completado no almacena datos personales.';
$string['processexpirationstask'] = 'Tarea de expiración de inscripción por curso completado';
$string['status'] = 'Habilitado';
$string['status_desc'] = 'Permitir inscripción por curso completado por defecto.';
$string['status_help'] = 'Esta configuración determina si la inscripción por curso completado está habilitada.';
$string['status_link'] = 'enrol/coursecompleted';
$string['svglearnpath'] = 'Mostrar ruta de aprendizaje';
$string['svglearnpath_help'] = 'Mostrar la (posible) ruta de aprendizaje usando íconos SVG.';
$string['tryunenrol'] = 'Dar de baja del curso completado.';
$string['tryunenrol_help'] = 'Intentar dar de baja automáticamente al usuario del curso completado.
Si el usuario fue inscrito con un método que permite darse de baja, este plugin intentará darlo de baja automáticamente.';
$string['unenrolusers'] = 'Dar de baja usuarios';
$string['uponcompleting'] = 'Al completar el curso {$a}';
$string['usersenrolled'] = '{$a} usuarios inscritos';
$string['welcome'] = 'Enviar mensaje de bienvenida al curso';
$string['welcome_help'] = 'Cuando un usuario se inscribe en un curso al completar otro, se puede enviar un correo de bienvenida.';
$string['welcometocourse'] = '¡Bienvenido a {$a->coursename}!

¡Felicidades!

Después de completar exitosamente {$a->completed}, ahora estás inscrito automáticamente en el curso {$a->coursename}.';
$string['willbeenrolled'] = 'Serás inscrito en este curso cuando completes el curso {$a}';
