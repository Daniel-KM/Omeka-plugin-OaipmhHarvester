<?php
/**
 * OaipmhHarvester_Test_AppTestCase - represents the base class for OaipmhHarvester tests.
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @package OaipmhHarvester
 */
class OaipmhHarvester_Test_AppTestCase extends Omeka_Test_AppTestCase
{
    const PLUGIN_NAME = 'OaipmhHarvester';

    public function setUp()
    {
        parent::setUp();

        // Authenticate and set the current user
        $this->user = $this->db->getTable('User')->find(1);
        $this->_authenticateUser($this->user);

        $pluginHelper = new Omeka_Test_Helper_Plugin;
        $pluginHelper->setUp(self::PLUGIN_NAME);
        Omeka_Test_Resource_Db::$runInstaller = true;

        // Remove the default item.
        $items = $this->db->getTable('Item')->findAll();
        foreach ($items as $item) {
            $item->delete();
        }
    }

    public function assertPreConditions()
    {
        $harvests = $this->db->getTable('OaipmhHarvester_Harvest')->findAll();
        $this->assertEquals(0, count($harvests), 'There should be no harvests.');

        $items = $this->db->getTable('Item')->findAll();
        $this->assertEquals(0, count($items), 'There should be no items.');
    }

    protected function _deleteAllHarvests()
    {
        $harvests = $this->db->getTable('OaipmhHarvester_Harvest')->findAll();
        foreach ($harvests as $harvest) {
            $harvest->delete();
        }
        $harvests = $this->db->getTable('OaipmhHarvester_Harvest')->findAll();
        $this->assertEquals(0, count($harvests), 'There should be no harvests.');
    }
}
