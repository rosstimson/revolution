<?php
/**
 * Gets a list of roles
 *
 * @param boolean $addNone If true, will add a role of None
 * @param integer $start (optional) The record to start at. Defaults to 0.
 * @param integer $limit (optional) The number of records to limit to. Defaults
 * to 10.
 * @param string $sort (optional) The column to sort by. Defaults to name.
 * @param string $dir (optional) The direction of the sort. Defaults to ASC.
 *
 * @package modx
 * @subpackage processors.security.role
 */
class modUserGroupRoleGetListProcessor extends modObjectGetListProcessor {
    public $classKey = 'modUserGroupRole';
    public $languageTopics = array('user');
    public $permission = 'view_role';
    public $defaultSortField = 'authority';
    public $canRemove = false;

    public function initialize() {
        $initialized = parent::initialize();
        $this->setDefaultProperties(array(
            'addNone' => false,
        ));
        if ($this->getProperty('sort') == 'rolename_link') $this->setProperty('sort','name');

        $this->canRemove = $this->modx->hasPermission('delete_role');
        return $initialized;
    }

    public function beforeIteration(array $list) {
        if ($this->getProperty('addNone',false)) {
            $list[] = array('id' => 0, 'name' => $this->modx->lexicon('none'));
        }
        return $list;
    }

    public function prepareRow(xPDOObject $object) {
        $objectArray = $object->toArray();
        $menu = array();
        if ($this->canRemove) {
            $menu[] = array(
                'text' => $this->modx->lexicon('role_remove'),
                'handler' => 'this.remove.createDelegate(this,["role_remove_confirm"])',
            );
        }
        $objectArray['menu'] = $menu;

        return $objectArray;
    }
}
return 'modUserGroupRoleGetListProcessor';