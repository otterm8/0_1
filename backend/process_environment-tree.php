<?php
define('QUADODO_IN_SYSTEM', true);
require_once '../includes/header.php';


if($_SERVER['REQUEST_METHOD'] == 'POST'){
	$qls->Security->check_auth_page('operator.php');
	require_once '../includes/Validate.class.php';
	
	$validate = new Validate($qls);
	//$validate->returnData['success'] = array();
	
	if ($validate->returnData['active'] == 'inactive') {
		echo json_encode($validate->returnData);
		return;
	}
	
	$data = json_decode($_POST['data'], true);
	validate($data, $validate, $qls);
	
	if (!count($validate->returnData['error'])){
		$operation = $data['operation'];
		
		if ($operation == 'create_node') {
			
			$parentID = $data['parent'];
			$nodeType = $data['type'];
			
			$attrArray = array('parent', 'type');
			$valueArray = array($parentID, $nodeType);
			
			if($nodeType == 'floorplan') {
				array_push($attrArray, 'floorplan_img');
				array_push($valueArray, DEFAULT_FLOORPLAN_IMG);
			}
			
			//Insert new node into env_tree table
			$qls->SQL->insert('app_env_tree', $attrArray, $valueArray);
			
			//Ajax response with auto-incremented node.id so jsTree can replace default 'j1_1' node.id
			$validate->returnData['success'] = $qls->SQL->insert_id();
			
		} else if ($operation == 'rename_node') {
			
			$nodeID = $data['id'];
			$nodeName = $data['name'];
			
			$qls->SQL->update('app_env_tree', array('name'=>$nodeName), 'id='.$nodeID);
			
		} else if ($operation == 'move_node') {
			
			$nodeID = $data['id'];
			$parentID = $data['parent'];
			
			$node = $qls->App->envTreeArray[$nodeID];
			
			$permitted = true;
			if($node['type'] == 'floorplan') {
				if($parentID != '#') {
					$parent = $qls->App->envTreeArray[$parentID];
					$parentType = $parent['type'];
					if($parentType == 'floorplan' or $parentType == 'pod' or $parentType == 'cabinet') {
						$permitted = false;
					}
				}
			} else if($node['type'] == 'cabinet') {
				if($parentID != '#') {
					$parent = $qls->App->envTreeArray[$parentID];
					$parentType = $parent['type'];
					if($parentType == 'cabinet' or $parentType == 'floorplan') {
						$permitted = false;
					}
				}
			}
			
			if($permitted) {
				$qls->SQL->update('app_env_tree', array('parent'=>$parentID), 'id='.$nodeID);
			} else {
				$errMsg = 'Invalid node move.';
				array_push($validate->returnData['error'], $errMsg);
			}
			
		} else if ($operation == 'delete_node') {
			
			$nodeID = $data['id'];
			$occupiedArray = array();
			
			$envTree = array();
			$query = $qls->SQL->select('*', 'app_env_tree');
			while($row = $qls->SQL->fetch_assoc($query)) {
				$envTree[$row['id']] = $row;
			}
			
			// Get the number of nodes that will be deleted
			$nodeCount = getNodeCount($nodeID, $qls);
			
			// Will there be anymore nodes left after deletion?
			if($nodeCount - count($envTree) == 0) {
				$errorMsg = 'Cannot delete all environment nodes.';
				array_push($validate->returnData['error'], $errorMsg);
			}
			
			// Does this node's children contain any objects?
			canDeleteNode($nodeID, $occupiedArray, $envTree, $qls);
			if(count($occupiedArray)) {
				$occupiedCabinetList = '';
				foreach($occupiedArray as $index => $occupiedNode) {
					$separator = $index == 0 ? '' : ', ';
					$occupiedCabinetList = $occupiedCabinetList.$separator.'<strong>'.$occupiedNode['name'].'</strong>';
				}
				$errorMsg = 'Cannot delete environment node.  The following cabinets are occupied by objects: '.$occupiedCabinetList;
				array_push($validate->returnData['error'], $errorMsg);
			}
			
			// If no errors, delete the environment node.
			if (!count($validate->returnData['error'])){
				deleteNodes($nodeID, $qls);
			}
			
		}
	}
	echo json_encode($validate->returnData);
} else {
	$qls->Security->check_auth_page('user.php');
	$treeArray = array();

	$treeData = $qls->SQL->select('*',
		    'app_env_tree',
			false,
			array('name', 'ASC')
		);
	while ($row = $qls->SQL->fetch_assoc($treeData)){
		$treeArray[] = array(
			'id' => $row['id'],
			'text' => $row ['name'],
			'parent' => $row['parent'],
			'type' => $row['type']);
	}

	header ('Content-Type: application/json');
	echo json_encode($treeArray);
}

function validate($data, &$validate, &$qls){
	$operationArray = array('create_node', 'rename_node', 'move_node', 'delete_node');
	
	// Validate the operation command
	if ($validate->validateInArray($data['operation'], $operationArray, 'operation.  Cannot delete the only remaining environment node.')) {
		$operation = $data['operation'];
		
		if ($operation == 'create_node') {
			
			$type = $data['type'];
			$parentID = $data['parent'];
			$typeArray = array('location', 'pod', 'cabinet', 'floorplan');
		
			$validate->validateInArray($type, $typeArray, 'node type.');
			$validate->validateTreeID($parentID);
			
			if($type == 'cabinet') {
				$query = $qls->SQL->select('id', 'app_env_tree', array('type' => array('=', 'cabinet')));
				$cabNum = $qls->SQL->num_rows($query) + 1;
			}
				
		} else if ($operation == 'rename_node') {
			
			$nodeName = $data['name'];
			$nodeID = $data['id'];
			
			$validate->validateNameText($nodeName, 'environment node name');
			$validate->validateTreeID($nodeID);
			
		} else if ($operation == 'move_node') {
			
			$parentID = $data['parent'];
			$nodeID = $data['id'];
			
			$validate->validateTreeID($parentID);
			$validate->validateTreeID($nodeID);
			
		} else if ($operation == 'delete_node') {
			
			$nodeID = $data['id'];
			
			$validate->validateTreeID($nodeID);
			
		}
	}
	
	return;
}

function canDeleteNode($id, &$occupiedArray, &$envTree, &$qls){

	$query = $qls->SQL->select('id', 'app_object', array('env_tree_id' => array('=', $id)));
	if($row = $qls->SQL->num_rows($query)) {
		array_push($occupiedArray, array('id' => $id, 'name' => $envTree[$id]['name']));
	}

	$query = $qls->SQL->select('*', 'app_env_tree', array('parent' => array('=', $id)));
	while($row = $qls->SQL->fetch_assoc($query)) {
		canDeleteNode($row['id'], $occupiedArray, $envTree, $qls);
	}
	return;
}

function deleteNodes($id, &$qls){
	$query = $qls->SQL->select('*', 'app_env_tree', array('parent' => array('=', $id)));
	while($row = $qls->SQL->fetch_assoc($query)) {
		deleteNodes($row['id'], $qls);
	}
	$qls->SQL->delete('app_env_tree', array('id' => array('=', $id)));
	return;
}

function getNodeCount($id, &$qls, &$count=0){
	$query = $qls->SQL->select('*', 'app_env_tree', array('parent' => array('=', $id)));
	while($row = $qls->SQL->fetch_assoc($query)) {
		getNodeCount($row['id'], $qls, $count);
	}
	$count = $count + 1;
	return $count;
}

?>
