<?php
class Twitter_upd
{
	public $version = '1.8.0';
	public $db_settings_table = 'cr_twitter_settings';

	public function __construct()
	{
		ee()->load->dbforge();
		$this->site_id = ee()->config->item('site_id');
	}

	public function install()
	{
		ee()->db->insert('modules', array(
			'module_name' => 'Twitter',
			'module_version' => $this->version,
			'has_cp_backend' => 'y',
			'has_publish_fields' => 'n'
		));

		ee()->load->dbforge();

		//create twitter module settings table
		$fields = array(
			'id'		=>	array('type' => 'int', 'constraint' => '10', 'unsigned' => TRUE, 'null' => FALSE, 'auto_increment' => TRUE),
			'site_id'	=>	array('type' => 'int', 'constraint' => '8', 'unsigned' => TRUE, 'null' => FALSE, 'default' => '1'),
			'var'		=>	array('type' => 'varchar', 'constraint' => '60', 'null' => FALSE),
			'var_value'	=>	array('type' => 'varchar', 'constraint' => '100', 'null' => FALSE)
		);

		ee()->dbforge->add_field($fields);
		ee()->dbforge->add_key('id', TRUE);
		ee()->dbforge->create_table($this->db_settings_table);

		// get the module id
		$results = ee()->db->query("SELECT * FROM exp_modules WHERE module_name = 'Twitter'");
		$module_id = $results->row('module_id');

		$sql = array();
		$sql[] =
					"INSERT IGNORE INTO exp_{$this->db_settings_table} ".
					"(id, site_id, var, var_value) VALUES ".
					"('', '0', 'module_id', " . $module_id . ")";

		return TRUE;
	}

	public function update( $current = '' )
	{
		if($current == $this->version) { return FALSE; }
		if($current != $this->version && $this->version == '1.4.2') {
			ee()->db->query("RENAME TABLE exp_twitter_settings TO exp_cr_twitter_settings");
		}
		return TRUE;
	}

	public function uninstall()
	{
		ee()->load->dbforge();

		ee()->db->query("DELETE FROM exp_modules WHERE module_name = 'Twitter'");

		ee()->dbforge->drop_table($this->db_settings_table);

		return TRUE;
	}
}

/* End of File: upd.module.php */
