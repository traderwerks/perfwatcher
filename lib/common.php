<?php # vim: set filetype=php fdm=marker sw=4 ts=4 et : 
/**
 * Common functions
 *
 * PHP version 5
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Monitoring
 * @author    Cyril Feraudet <cyril@feraudet.com>
 * @copyright 2011 Cyril Feraudet
 * @license   http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link      http://www.perfwatcher.org/
 */

require "etc/config.default.php";
require "lib/class._database.php";
require "lib/class.tree.php";
require_once("MDB2.php");

function get_arg($key, $default_value, $check_if_is_numeric, $die_error_message, $file="unset", $line="unset") {
    if(isset($_GET[$key])) {
        if($check_if_is_numeric && (! is_numeric($_GET[$key]))) {
            if($die_error_message) { error_log("$die_error_message ($file,$line)\n"); exit(1); }
            return($default_value); # return default if no die message.
        }
        return($_GET[$key]);
    } elseif(isset($_POST[$key])) {
        if($check_if_is_numeric && (! is_numeric($_POST[$key]))) {
            if($die_error_message) { error_log("$die_error_message ($file,$line)\n"); exit(1); }
            return($default_value); # return default if no die message.
        }
        return($_POST[$key]);
    }
    if($die_error_message) { error_log("$die_error_message ($file,$line)\n"); exit(1); }
    return($default_value); # return default if no die message.

}

function load_datas($host) {
    global $rrds_path, $grouped_type, $blacklisted_type;
    if (is_array($rrds_path)) {
        $array_rrds_path = $rrds_path;
    } else {
        $array_rrds_path = array($rrds_path);
    }
    $ret = array();
    foreach($array_rrds_path as $rrds_path) {
        if (!is_dir($rrds_path.'/'.$host)) { continue; }
        $dh = scandir($rrds_path.'/'.$host, 1);
        foreach ($dh as $plugindir) {
            if (!is_dir($rrds_path.'/'.$host.'/'.$plugindir) || $plugindir == '.' || $plugindir == '..') { continue; }
            $plugin = $plugin_instance = '';
            @list($plugin, $plugin_instance ) = split('-', $plugindir, 2);
            if ($plugin_instance == '') { $plugin_instance = '_'; }
            $ret[$plugin][$plugin_instance] = array();
            $dh2 = scandir($rrds_path.'/'.$host.'/'.$plugindir);
            foreach ($dh2 as $rrd) {
                if ($rrd == '.' || $rrd == '..' || substr($rrd, -4) != '.rrd') { continue; }
                $type = $type_instance = '';
                @list($type, $type_instance) = split('-', substr($rrd,0, -4), 2);
                if (in_array($type, $blacklisted_type)) { continue; }
                if ($type_instance == '') { $type_instance = '_'; }
                //if ($type == $plugin) { $type_instance = '_'; }
                if (in_array($type,$grouped_type)) {
                    $ret[$plugin][$plugin_instance][$type]['_'] = true;
                } else {
                    $ret[$plugin][$plugin_instance][$type][$type_instance] = true;
                }
                ksort($ret[$plugin]);
            }
        }
    }
    ksort($ret);
    return $ret;
}

function purge_data() {

}

function get_widget($datas) {
    global $widgets;
    $json = array();
    foreach($widgets as $widget) {
        if (!file_exists("lib/class.$widget.php")) { continue; }
        if (class_exists ($widget)) { continue; }
        if (!include ("lib/class.$widget.php")) { continue; }
        if (!class_exists ($widget)) { continue; }
        $owidget = new $widget($datas);
        if (!$owidget->is_compatible()) { continue; }
        $json[$widget] = $owidget->get_info();
    }
    return $json;
}

function split_pluginstr($pluginstr) {
    if (strpos($pluginstr, '/') === false) {
        return array('', '', '', '');
    }
    list($g, $d) = split('/', $pluginstr, 2);
    if (strpos($g, '-') !== false) {
        list($plugin, $plugin_instance) = split('-', $g, 2);
    } else { $plugin = $g; $plugin_instance = ''; }
    if (strpos($d, '-') !== false) {
        list($type, $type_instance) = split('-', $d, 2);
    } else { $type = $d; $type_instance = ''; }
    return array($plugin, $plugin_instance, $type, $type_instance);
}

function get_nodes_count($host_id)
{
    global $jstree, $childrens_cache;
    $nodes = 0;
    $childrens = $jstree->_get_children($host_id, true);
    foreach($childrens as $children) {
        if ($children['type'] != 'default') {
            continue;
        }
        $nodes++;
    }
    return $nodes;
}

function create_new_view($title) {
    global $db_config;
    $view_id = -1;
    $id = -1;
    $db = new _database($db_config);
    if ($db->connect()) {
        $result_connect = 1;
        $db->prepare("INSERT INTO tree (view_id, parent_id, position, type, title) SELECT MAX(view_id)+1, 1, 0, 'folder', ? FROM tree", array('text'));
        $db->execute($title);
        $id = $db->insert_id('tree', 'id');
        $db->prepare("SELECT distinct view_id FROM tree WHERE id = ?", array('integer'));
        $db->execute((int)$id);
        if($db->nextr()) {
            $r = $db->get_row('assoc');
            $view_id = $r['view_id'];
        }
        $db->destroy();
    }
    return(array($id,$view_id));
}

function list_views($maxrows, $startswith) {
    global $db_config;
    $r = array();
    $db = new _database($db_config);
    if ($db->connect()) {
        $result_connect = 1;
        $startswith = $startswith."%";
        $db->prepare("SELECT view_id,title FROM tree WHERE parent_id = 1 AND title LIKE ? ORDER BY title LIMIT ?", array('text', 'integer'));
        $db->execute(array($startswith, $maxrows));
        while($db->nextr()) {
            $v = $db->get_row('assoc');
            $r[] = $v;
        }
        $db->destroy();
    }
    return($r);
}

function delete_view($view_id) {
    global $db_config;
    $result_view_id = 0;
    $db = new _database($db_config);
    if ($db->connect()) {
        $result_connect = 1;
        $db->prepare("DELETE FROM tree WHERE view_id = ?", array('integer'));
        $db->execute($view_id);
        $db->prepare("SELECT MIN(view_id) AS v FROM tree");
        $db->execute();
        if($db->nextr()) {
            $r = $db->get_row('assoc');
            $result_view_id = $r['v'];
        }
        $db->destroy();
    }
    return($result_view_id);
}

function get_view_id_from_id($id) {
    global $db_config;
    $view_id = -1;
    $db = new _database($db_config);
    if ($db->connect()) {
        $result_connect = 1;
        $db->prepare("SELECT distinct view_id FROM tree WHERE id = ?", array('integer'));
        $db->execute((int)$id);
        if($db->nextr()) {
            $r = $db->get_row('assoc');
            $view_id = $r['view_id'];
        }
        $db->destroy();
    }
    return($view_id);
}

function get_node_name_by_id($id) {
    global $db_config;
    $title = "";
    $id = substr($id, 11);
    $db = new _database($db_config);
    if ($db->connect()) {
        $result_connect = 1;
        $db->prepare("SELECT title FROM tree WHERE id = ?", array('integer'));
        $db->execute((int)$id);
        if($db->nextr()) {
            $r = $db->get_row('assoc');
            $title = $r['title'];
        }
        $db->destroy();
    }
    return $title;
}

function get_node_name($id) {
    if (substr($id, 0, 11) != 'aggregator_') { return($id); }
    return get_node_name_by_id($id);
}

?>
