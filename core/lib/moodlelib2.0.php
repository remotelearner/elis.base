<?php
/**
 * Functions defined in moodlelib in Moodle 2.0
 */

/**
 * Return exact path to plugin directory
 * @param string $plugintype type of plugin
 * @param string $name name of the plugin
 * @param bool $fullpaths false means relative paths from dirroot
 * @return directory path, full or relative to dirroot
 */
function get_plugin_directory($plugintype, $name, $fullpaths=true) {
    if ($plugintype === '') {
        $plugintype = 'mod';
    }

    $types = get_plugin_types($fullpaths);
    if (!array_key_exists($plugintype, $types)) {
        return null;
    }
    $name = clean_param($name, PARAM_SAFEDIR); // just in case ;-)

    return $types[$plugintype].'/'.$name;
}

/**
 * Return exact path to plugin directory,
 * this method support "simpletest_" prefix designed for unit testing.
 * @param string $component name such as 'moodle', 'mod_forum' or special simpletest value
 * @param bool $fullpaths false means relative paths from dirroot
 * @return directory path, full or relative to dirroot
 */
function get_component_directory($component, $fullpaths=true) {
    global $CFG;

    $simpletest = false;
    if (strpos($component, 'simpletest_') === 0) {
        $subdir = substr($component, strlen('simpletest_'));
        return $subdir;
    }
    if ($component == 'moodle') {
        $path = ($fullpaths ? $CFG->libdir : 'lib');
    } elseif ($component == 'local') {
        $path = ($fullpaths ? ($CFG->dirroot.'/') : '') . 'local';
    } else {
        list($type, $plugin) = explode('_', $component, 2);
        $path = get_plugin_directory($type, $plugin, $fullpaths);
    }        

    return $path;
}

/**
 * Lists all plugin types
 * @param bool $fullpaths false means relative paths from dirroot
 * @return array Array of strings - name=>location
 */
function get_plugin_types($fullpaths=true) {
    global $CFG;

    static $info     = null;
    static $fullinfo = null;

    if (!$info) {
        $info = array('mod'           => 'mod',
                      'auth'          => 'auth',
                      'enrol'         => 'enrol',
                      'message'       => 'message/output',
                      'block'         => 'blocks',
                      'filter'        => 'filter',
                      'editor'        => 'lib/editor',
                      'format'        => 'course/format',
                      'import'        => 'course/import',
                      'profilefield'  => 'user/profile/field',
                      'report'        => $CFG->admin.'/report',
                      'coursereport'  => 'course/report', // must be after system reports
                      'gradeexport'   => 'grade/export',
                      'gradeimport'   => 'grade/import',
                      'gradereport'   => 'grade/report',
                      'repository'    => 'repository',
                      'portfolio'     => 'portfolio/type',
                      'qtype'         => 'question/type',
                      'qformat'       => 'question/format',
                      'elis'          => 'elis',
                      'crlm'          => 'curriculum/plugins');

        // do not include themes if in non-standard location
        if (empty($CFG->themedir) or $CFG->themedir === $CFG->dirroot.'/theme') {
            $info['theme'] = 'theme';
        }

        // local is always last
        $info['local'] = 'local';

        $fullinfo = array();
        foreach ($info as $type => $dir) {
            $fullinfo[$type] = $CFG->dirroot.'/'.$dir;
        }
        $fullinfo['theme'] = $CFG->themedir;
    }

    return ($fullpaths ? $fullinfo : $info);
}
?>
