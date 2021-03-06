<?php # vim: set filetype=php fdm=marker sw=4 ts=4 et : 
/**
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

header("HTTP/1.0 200 OK");
header('Content-type: text/json; charset=utf-8');
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");

$id = get_arg('id', 0, 1, "Error : No valid id found !!!", __FILE__, __LINE__);
$view_id = get_arg('view_id', 0, 1, "Error : No valid view_id found !!!", __FILE__, __LINE__);

$jstree = new json_tree($view_id);
$res = $jstree->_get_node($id);
$datas = $jstree->get_datas($res['id']);

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'save_tab':
            $datas['tabs'][$_POST['tab_id']]['selected_graph'] = $_POST['selected_graph'];
            $datas['tabs'][$_POST['tab_id']]['selected_hosts'] = $_POST['selected_hosts'];
            $jstree->set_datas($res['id'], $datas);
            break;
    }
}

echo json_encode(
        array(
            'message' => "View succefully saved."
            ));
?>
