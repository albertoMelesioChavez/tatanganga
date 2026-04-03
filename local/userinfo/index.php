<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

admin_externalpage_setup('local_userinfo_browse');

$PAGE->set_url(new moodle_url('/local/userinfo/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_userinfo'));
$PAGE->set_heading(get_string('pluginname', 'local_userinfo'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_userinfo'));

// Search form
echo '<form method="get" class="mb-3">';
echo '<div class="row">';
echo '<div class="col-md-6">';
echo '<input type="text" name="search" class="form-control" placeholder="Buscar por nombre, email o username" value="' . s(optional_param('search', '', PARAM_TEXT)) . '">';
echo '</div>';
echo '<div class="col-md-3">';
echo '<button type="submit" class="btn btn-primary">Buscar</button>';
echo ' <a href="' . $PAGE->url->out(false) . '" class="btn btn-secondary">Limpiar</a>';
echo '</div>';
echo '</div>';
echo '</form>';

$search = optional_param('search', '', PARAM_TEXT);

$sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.confirmed, u.suspended, u.lastaccess
        FROM {user} u
        WHERE u.deleted = 0 AND u.id != :guestid";

$params = ['guestid' => $CFG->siteguest];

if (!empty($search)) {
    $sql .= " AND (" . $DB->sql_like('u.firstname', ':search1', false) . 
            " OR " . $DB->sql_like('u.lastname', ':search2', false) .
            " OR " . $DB->sql_like('u.email', ':search3', false) .
            " OR " . $DB->sql_like('u.username', ':search4', false) . ")";
    $searchparam = '%' . $DB->sql_like_escape($search) . '%';
    $params['search1'] = $searchparam;
    $params['search2'] = $searchparam;
    $params['search3'] = $searchparam;
    $params['search4'] = $searchparam;
}

$sql .= " ORDER BY u.lastname, u.firstname";

$users = $DB->get_records_sql($sql, $params, 0, 100); // Limit to 100 users

if (empty($users)) {
    echo '<div class="alert alert-info">No se encontraron usuarios.</div>';
} else {
    echo '<div class="alert alert-info">Mostrando ' . count($users) . ' usuarios (máximo 100)</div>';
    
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped table-hover">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Usuario</th>';
    echo '<th>Email</th>';
    echo '<th>Estado</th>';
    echo '<th>Roles</th>';
    echo '<th>Cursos</th>';
    echo '<th>Stripe</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($users as $user) {
        echo '<tr>';
        
        // User info
        echo '<td>';
        $user->firstnamephonetic = '';
        $user->lastnamephonetic = '';
        $user->middlename = '';
        $user->alternatename = '';
        echo '<strong>' . fullname($user) . '</strong><br>';
        echo '<small class="text-muted">' . s($user->username) . '</small>';
        echo '</td>';
        
        // Email
        echo '<td>' . s($user->email) . '</td>';
        
        // Status
        echo '<td>';
        if ($user->suspended) {
            echo '<span class="badge bg-warning">Suspendido</span><br>';
        } else {
            echo '<span class="badge bg-success">Activo</span><br>';
        }
        if (!$user->confirmed) {
            echo '<span class="badge bg-danger">Email no confirmado</span>';
        } else {
            echo '<span class="badge bg-success">Email confirmado</span>';
        }
        echo '</td>';
        
        // Roles
        echo '<td>';
        $roles = $DB->get_records_sql("
            SELECT DISTINCT r.id as roleid, r.shortname, r.name, ctx.contextlevel
            FROM {role} r
            JOIN {role_assignments} ra ON ra.roleid = r.id
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE ra.userid = :userid
            ORDER BY ctx.contextlevel, r.sortorder
        ", ['userid' => $user->id]);
        
        if (empty($roles)) {
            echo '<span class="text-muted">Sin roles</span>';
        } else {
            foreach ($roles as $role) {
                $rolename = !empty($role->name) ? format_string($role->name) : $role->shortname;
                $badge = 'secondary';
                
                if ($role->shortname === 'student_suscriptor') {
                    $badge = 'success';
                    $rolename .= ' ⭐';
                } elseif (in_array($role->shortname, ['manager', 'coursecreator'])) {
                    $badge = 'primary';
                }
                
                echo '<span class="badge bg-' . $badge . ' me-1 mb-1">' . $rolename . '</span>';
            }
        }
        echo '</td>';
        
        // Courses
        echo '<td>';
        $courses = $DB->get_records_sql("
            SELECT COUNT(DISTINCT c.id) as total
            FROM {course} c
            JOIN {enrol} e ON e.courseid = c.id
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE ue.userid = :userid AND c.id > 1
        ", ['userid' => $user->id]);
        
        $total = reset($courses);
        if ($total && $total->total > 0) {
            echo '<span class="badge bg-info">' . $total->total . ' cursos</span>';
        } else {
            echo '<span class="text-muted">0 cursos</span>';
        }
        echo '</td>';
        
        // Stripe
        echo '<td>';
        $customerid = get_user_preferences('local_stripe_customer_id', null, $user->id);
        if ($customerid) {
            echo '<span class="badge bg-success" title="' . s($customerid) . '">✓ Stripe</span>';
        } else {
            echo '<span class="text-muted">-</span>';
        }
        echo '</td>';
        
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

echo $OUTPUT->footer();
