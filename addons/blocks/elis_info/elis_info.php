<?php // $Id$
/**
 * elis_info.php - display elis component information
 *
 * @uses $COURSE, $CFG, $USER
 * @author Brent Boghosian <brent.boghosian@remote-learner.net>
 * @version 1.0
 * @package block elis_info
 *
 **/

require_once(dirname(__FILE__)."/../../config.php" );
require_once("{$CFG->dirroot}/blocks/moodleblock.class.php");

global $COURSE, $USER;

$strelisinfo = get_string('elis_info', 'block_elis_info');

// Build navigation
$navlinks = array();
//$navlinks[] = array('name' => $strelisinfo);
$navlinks[] = array('name' => $strelisinfo, 'link' => null, 'type' => 'misc');
$navigation = build_navigation($navlinks);
if (!($site = get_site())) {
    print_error('no_site', 'block_elis_info');
}
print_header($site->fullname, $strelisinfo, $navigation, '', '', true, '');

// if ($COURSE->id != SITEID) { TBD }
$context = get_context_instance(CONTEXT_SYSTEM, SITEID); // SYSTEM context
if (!has_capability('block/elis_info:view', $context)) {
    print_error('no_privilege', 'block_elis_info');
}

echo "<center><br/>\n";
echo '<table class="elis_info" cellpadding="3" cellspacing="3" border="1"><tr class="elis_info">'."\n";
echo '  <th class="elis_info">'.get_string('elis_component', 'block_elis_info').
     "</th>\n";
echo '  <th class="elis_info">'.get_string('elis_version', 'block_elis_info').
     "</th>\n";
echo "</tr>\n";

$plugins = array();
$cnt = 0;
$authplugins  = get_list_of_plugins('auth');
foreach ($authplugins as $authplugin) {
    $plugins[$cnt] = new stdClass;
    $plugins[$cnt]->dir = "auth/{$authplugin}";
    $plugins[$cnt]->obj = get_auth_plugin($authplugin);
    ++$cnt;
}
$blockplugins = get_list_of_plugins('blocks');
foreach ($blockplugins as $blockplugin) {
    $plugins[$cnt] = new stdClass;
    $plugins[$cnt]->dir = "blocks/{$blockplugin}";
    $plugins[$cnt]->obj = "block_{$blockplugin}";
    ++$cnt;
}
$coursefmtplugins = get_list_of_plugins('course/format');
foreach ($coursefmtplugins as $coursefmtplugin) {
    $plugins[$cnt] = new stdClass;
    $plugins[$cnt]->dir = "course/format/{$coursefmtplugin}";
    // TBD: no object for course/format?
    ++$cnt;
}
$enrolplugins = get_list_of_plugins('enrol');
// require class enrolment_factory for enrolplugin
require_once("{$CFG->dirroot}/enrol/enrol.class.php");
foreach ($enrolplugins as $enrolplugin) {
    $plugins[$cnt] = new stdClass;
    $plugins[$cnt]->dir = "enrol/{$enrolplugin}";
    $plugins[$cnt]->obj = enrolment_factory::factory($enrolplugin);
    ++$cnt;
}
$filterplugins = get_list_of_plugins('filter');
foreach ($filterplugins as $filterplugin) {
    $plugins[$cnt] = new stdClass;
    $plugins[$cnt]->dir = "filter/{$filterplugin}";
    // TBD: no object for filters?
    ++$cnt;
}
$modplugins = get_list_of_plugins('mod');
foreach ($modplugins as $modplugin) {
    $plugins[$cnt] = new stdClass;
    $plugins[$cnt]->dir = "mod/{$modplugin}";
    $plugins[$cnt]->obj = "mod_{$modplugin}";
    ++$cnt;
}
$assigntypeplugins  = get_list_of_plugins('mod/assignment/type');
// require class assignment_base for assigntypeplugin
require_once("{$CFG->dirroot}/mod/assignment/lib.php");
foreach ($assigntypeplugins as $assigntypeplugin) {
    $plugins[$cnt] = new stdClass;
    $plugins[$cnt]->dir = "mod/assignment/type/{$assigntypeplugin}";
    $classname = "assignment_{$assigntypeplugin}";
    $classfile = "{$CFG->dirroot}/{$plugins[$cnt]->dir}/assignment.class.php";
    if (!class_exists($classname) && file_exists($classfile)) {
        require_once($classfile);
    }
    if (class_exists($classname)) {
        $plugins[$cnt]->obj = new $classname;
    }
    ++$cnt;
}
//var_dump($plugins);
foreach ($plugins as $curplugin) {
    $versionfile = "{$CFG->dirroot}/{$curplugin->dir}/version.php";
    $plugin_obj = new stdClass;
    if (file_exists($versionfile)) {
        if (is_readable($versionfile)) {
            //error_log("blocks/elis_info.php: checking version file '{$versionfile}'");
            $plugin = new stdClass;
            $module = new stdClass;
            include($versionfile); // include_once() fails for 'mod'
            $plugin_obj = property_exists($module, 'release')
                          ? $module : $plugin; // modules : filters
        } else {
            error_log("blocks/elis_info.php: version file '$versionfile' not readable!");
        }
    } else if (property_exists($curplugin, 'obj')) {
        if (is_object($curplugin->obj)) {
            $plugin_obj = $curplugin->obj; // auth, enrol?
        } else {
            $classfile = "{$CFG->dirroot}/{$curplugin->dir}/{$curplugin->obj}.php";
            if (!class_exists($curplugin->obj) &&
                file_exists($classfile)) {
                include_once($classfile);
            }
            if (class_exists($curplugin->obj)) {
                $plugin_obj = new $curplugin->obj; // blocks ...
            }
        }
    }
    if (property_exists($plugin_obj, 'release')) {
        echo "<tr><td class=\"elis_info\">{$curplugin->dir}</td>\n";
        echo "    <td class=\"elis_info\">{$plugin_obj->release}</td>\n</tr>\n";
    }
}

echo "</table>\n";
echo "</center>\n";
print_footer($COURSE);

?>
