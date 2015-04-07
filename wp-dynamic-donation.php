<?php
/**
 * @package Donation
 * @author Faaiq Ahmed
 * @version 1.0
 */
/*
Plugin Name: Dynamic Donation
Description: Dynamic Donation Form,.
Author: Faaiq Ahmed, Technical Architect PHP, nfaaiq@gmail.com
Version: 1.0
*/

global $ddf_db_version;	
$ddf_db_version = "2.5";

class dynamic_donation {
	var $field_table = '';
	function __construct() {		global $wpdb;
		
		$this->field_table = $wpdb->prefix . "ddf_fields";
		add_action('admin_menu', array($this,'ddf_menu'));
		add_action('init', array($this,'create_content_type'));
		
		add_action('wp_ajax_ddf_field_status', array($this,'ddf_field_status'));
		
		add_action('wp_ajax_ddf_field_new', array($this,'ddf_field_new'));
		
		add_action('wp_ajax_ddf_field_delete', array($this,'ddf_field_delete'));
		
		add_action('wp_ajax_ddf_field_edit', array($this,'ddf_field_edit'));
		
		
		
		add_action('admin_head', array($this,'admin_load_js'));
		register_activation_hook(__FILE__, array($this,'ddf_install'));
				register_deactivation_hook(__FILE__, array($this,'ddf_uninstall'));
		
		add_action( 'admin_init', array($this,'register_mysettings'));
		
		add_shortcode( 'render_donation_form', array($this, 'render_donation_form' ));
		
		add_action( 'template_redirect', array($this,'handle_submit' ));
		
		add_action( 'add_meta_boxes', array($this, 'fields_add_meta_box' ));
	}
	
	
	function ddf_menu() {

		global $current_user, $wpdb;

		///add_menu_page('Donation List', 'Donation List', 'administrator', 'ddf', array($this,'post_order_category'));

		add_submenu_page( "edit.php?post_type=donation-form", "Manage Fields", "Manage Fields", 'administrator', "subddf", array($this,"manage_fields") );
		
		add_submenu_page( "edit.php?post_type=donation-form", "Payment Settings", "Payment Settings", 'administrator', "subddfp", array($this,"payment_settings") );
		
		add_submenu_page( "edit.php?post_type=donation-form", "Export Template", "Export Template", 'administrator', "subddft", array($this,"export_template") );
		
	}
	
	
	function fields_add_meta_box(  ) {
		
		add_meta_box('donation-form-fields',__( 'Donation Information', 'myplugin_textdomain' ), array($this,'myplugin_meta_box_callback'),'donation-form');
	}
	
	
	function myplugin_meta_box_callback($post) {
		global $wpdb;
		// Add an nonce field so we can check for it later.
		
		$fields_arr  = get_post_custom($post->ID);
		
		$fields_temp = array();
		foreach($fields_arr as $k => $v) {
			$fields_temp[$k] = $k;
		}
		
		wp_nonce_field( 'myplugin_meta_box', 'myplugin_meta_box_nonce' );
		$fields_rows = $wpdb->get_results("select * from " . $this->field_table . " order by id");
		echo '<table class="form-table">';
		

		for($i = 0; $i < count($fields_rows); ++$i) {
			if(in_array($fields_rows[$i]->field_name,$fields_temp)) {
				unset($fields_temp[$fields_rows[$i]->field_name]);
			}
			
			
			$value = get_post_meta( $post->ID, $fields_rows[$i]->field_name, true );
			echo '<tr>';
			echo '<td>';
			_e( $fields_rows[$i]->field_label, 'myplugin_textdomain' );
			echo '</td>&nbsp;&nbsp;';
			echo '<td>';
			echo '<b>'.$value .'</b>' ;
			echo '</td>';
			echo '</tr>';
		}
		
		
		foreach($fields_temp as $k => $v) {
			$value = get_post_meta( $post->ID, $k, true );
			if($k == '_edit_lock') {
				continue;
			}
			echo '<tr>';
			echo '<td>';
			_e( $k, 'myplugin_textdomain' );
			echo '</td>&nbsp;&nbsp;';
			echo '<td>';
			echo '<b>'.$value .'</b>' ;
			echo '</td>';
			echo '</tr>';
			
			
		}
		echo '</table>';
		
	}

	
	function handle_submit() {
		global $wpdb;
		//print_r($_REQUEST);
		if(isset($_REQUEST['donation'])) {
			$donation = $_REQUEST['donation'];
			if($donation == 'notify_url') {
				$pid = $_REQUEST['p'];
				foreach($_REQUEST as $p => $v ) {
					if(is_array($v)) {
						$meta_value = implode(",",$v);
					}else {
						$meta_value = $v;
					}
					update_post_meta($pid, $p, $meta_value);
				}
			}
			if($donation == 'success') {
				$pid = $_REQUEST['p'];
				foreach($_REQUEST as $p => $v ) {
					if(is_array($v)) {
						$meta_value = implode(",",$v);
					}else {
						$meta_value = $v;
					}
					
					update_post_meta($pid, $p, $meta_value);
				}
				$url = home_url(get_option('_ddf_thanks_page','donation-thanks'));
				wp_redirect($url);
				//exit;
			}

			if($donation == 'failed') {
				$pid = $_REQUEST['p'];
				foreach($_REQUEST as $p => $v ) {
					if(is_array($v)) {
						$meta_value = implode(",",$v);
					}else {
						$meta_value = $v;
					}
					
					update_post_meta($pid, $p, $meta_value);
				}
				$url = home_url(get_option('_ddf_error_page','donation-failed'));
				wp_redirect($url);
				//exit;
			}			
			
			
		}
		if(isset($_POST)) {
			if(isset($_POST['donation-form-submission'])) {
				
				$fields_row = $wpdb->get_results("select * from " . $this->field_table . " where enable = 1");
				
				$data = array();
				
				for($i = 0; $i < count($fields_row); ++$i) {
					$data[$fields_row[$i]->field_name] = $_POST[$fields_row[$i]->field_name];
				}
				$this->create_donation($data);
			}
		}
		
		
	}

	function create_donation($data) {
		$my_post = array(
			'post_title'    => 'Donation ' . date('m-d-Y H:i:s'),
			'post_type'   => 'donation-form',
			'post_status'   => 'private',
			'post_author'   => 1,
		);	
		$this->post_id = wp_insert_post( $my_post , $wp_error );
		
		
		foreach($data as $k => $v) {
			if(is_array($v)) {
				$meta_value = implode(",",$v);
			}else {
				$meta_value = $v;
			}
			update_post_meta($this->post_id, $k, $meta_value);
		}
		
		$this->process_payment();

	}
	
	function process_payment() {
		global $wpdb;
		$payment = $_POST['submit'];
		
		$this->gateway_type = get_option('_ddf_paypal_sandbox','test');
		
		if(strtolower($payment) == 'paypal') {
			
			$amount = $_POST['amount'];
			
			if($this->gateway_type == 'test') {
				$this->api_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
			}else {
				$this->api_url = "https://www.paypal.com/cgi-bin/webscr";
			}
			$html = '';
			$html .= '<form action="'.$this->api_url.'" method="post" id="payment_process">';
			$html .= '<input type="hidden" name="cmd" value="_donations">';
			$html .= '<input type="hidden" name="business" value="'.  get_option('_ddf_paypal_email') .'">';
			$html .= '<input type="hidden" name="item_name" value="Donation">';
			$html .= '<input type="hidden" name="item_number" value="0001">';
			$html .= '<input type="hidden" name="amount" value="'.$amount.'">';
			$html .= '<input type="hidden" name="rm" value="2">';
			
			$html .= '<INPUT TYPE="hidden" NAME="notify_url" value="'.home_url("/").'?donation=notify_url&p='.$this->post_id.'">';
			$html .= '<INPUT TYPE="hidden" NAME="return" value="'.home_url("/").'?donation=success&p='.$this->post_id.'">';
			$html .= '<INPUT TYPE="hidden" NAME="cancel_return" value="'.home_url(get_option('_ddf_error_page')).'?donation=failed">';
			
			$html .= '<input type="hidden" name="currency_code" value="USD">';
			$html .= '</form>';
			$html .= '<script>';
			$html .= 'document.getElementById("payment_process").submit();';
			$html .= '</script>';
			print $html;
			exit;
		}
	}
	

	
	
	function ddf_paypal_api_request($request, $server) {
		// We use $request += to add API credentials so that if a key already exists,
		// it will not be overridden.
		$request += array(
		  'USER' => get_option('_ddf_pro_api_username', ''),
		  'PWD' => get_option('_ddf_pro_api_password', ''),
		  'VERSION' => '3.0',
		  'SIGNATURE' => get_option('_ddf_pro_api_singnature', ''),
		);
	  
		$data = '';
		foreach ($request as $key => $value) {
		  $data .= $key . '=' . urlencode(str_replace(',', '', $value)) . '&';
		}
		$data = substr($data, 0, -1);
	  
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $server);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($ch, CURLOPT_NOPROGRESS, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		$response = curl_exec($ch);
		if ($error = curl_error($ch)) {
		  watchdog('uc_paypal', '!error', array('!error' => $error), WATCHDOG_ERROR);
		}
		curl_close($ch);
	  
		return $this->_ddf_paypal_nvp_to_array($response);
	  }

	function _ddf_paypal_nvp_to_array($nvpstr) {
	  foreach (explode('&', $nvpstr) as $nvp) {
		list($key, $value) = explode('=', $nvp);
		$nvp_array[urldecode($key)] = urldecode($value);
	  }
	
	  return $nvp_array;
	}
	
	// [bartag foo="foo-value"]
	function render_donation_form( $atts ) {
		if(isset($_POST)) {
			foreach($_POST as $k => $v) {
				$$k = $_POST[$k];
			}
			
		}
		
		$theme_path =  get_template_directory();
		$form_path = $theme_path . '/' . 'template-dynamic-donation-form.php';
		if(file_exists($form_path)) {
			ob_start();
			require_once($form_path);	
			$html = ob_get_contents();
			ob_end_clean();
			
		}else {
			$html = 'Error : Please export template from donation menu and create a file  template-dynamic-donation-form.php in your theme directory';
		}
		
	
		return $html;
	}
	
	
	
	function export_template() {
		global $wpdb;
		
		$fields_row = $wpdb->get_results("SELECT * FROM " . $this->field_table ." WHERE enable = 1 order by id");
		$form = '<form  method="post">' .chr(13);
		$form .= '<input type="hidden" name="donation-form-submission" value="1">' .chr(13);
		for($i =0 ; $i < count($fields_row); ++$i) {
			$form .= '<div class="form-control">' . chr(13);
			
			$form .= chr(9) . '<div class="form-label">' . chr(13);
			$form .= chr(9) . chr(9) . $fields_row[$i]->field_label. chr(13);
			$form .= chr(9) . '</div>' . chr(13);
			
			$form .= chr(9) . '<div class="form-field">' . chr(13);
			
			if($fields_row[$i]->field_type == 'text') {
				$form .=  chr(9) . chr(9) . '<input type="text" name="'.$fields_row[$i]->field_name.'" id="'.$fields_row[$i]->field_name.'" value="<?php print @$'. $fields_row[$i]->field_name . ';?>" size="30">' . chr(13);
			}else if($fields_row[$i]->field_type == 'select') {
				$field_options = explode(",",$fields_row[$i]->field_options);
				
				
				$form .= chr(9) . chr(9) . '<select name="'.$fields_row[$i]->field_name.'" id="'.$fields_row[$i]->field_name.'">' . chr(13);
				for($fo = 0; $fo < count($field_options); ++$fo) {
					list($label,$opt) = explode("|",$field_options[$fo]);
					if($opt && $label) {
						$form .= chr(9) . chr(9) . chr(9) . '<option <?php print ($'.$fields_row[$i]->field_name.' == 1) ? \'selected\' : \'\'; ?> value="'.$opt.'">'.$label.'</option>' . chr(13);
					}
				}
				$form .= chr(9) . chr(9) . '</select>' . chr(13);
			}else if($fields_row[$i]->field_type == 'checkbox') {
				$field_options = explode(",",$fields_row[$i]->field_options);
				
				$form .= chr(9) . chr(9) . '<?php if(!isset($'.$fields_row[$i]->field_name.')) {$'.$fields_row[$i]->field_name.' = array();} ?>';
				for($fo = 0; $fo < count($field_options); ++$fo) {
					list($label,$opt) = explode("|",$field_options[$fo]);
					if($opt && $label) {
						$form .= chr(9) . chr(9) . '<input <?php print (in_array('.$opt.',$'.$fields_row[$i]->field_name.')) ? \'checked\' : \'\'; ?> type="checkbox" value="'.$opt.'" name="'.$fields_row[$i]->field_name.'[]" id="'.$fields_row[$i]->field_name.'1" > ' . $label . chr(13);
					}
				}
				
			}else if($fields_row[$i]->field_type == 'radio') {
				$field_options = explode(",",$fields_row[$i]->field_options);
				for($fo = 0; $fo < count($field_options); ++$fo) {
				list($label,$opt) = explode("|",$field_options[$fo]);
					if($opt && $label) {
						$form .= chr(9) . chr(9) . '<input  <?php print ($'.$fields_row[$i]->field_name.' == '.$opt.') ? \'checked\' : \'\'; ?> type="radio" value="'.$opt.'" name="'.$fields_row[$i]->field_name.'" id="'.$fields_row[$i]->field_name.'1" > '. $label . chr(13);
					}
				}
				
			}else if($fields_row[$i]->field_type == 'textarea') {
				$form .= chr(9) . chr(9) . '<textarea name="'.$fields_row[$i]->field_name.'" id="'.$fields_row[$i]->field_name.'" rows"3" cols="40"><?php print $'.$fields_row[$i]->field_name.'; ?></textarea>' . chr(13);
			}
			
			$form .= chr(9) . '</div>' . chr(13);
			
			$form .= '</div>' . chr(13);
		}
		
		$form .= $this->get_payment_form();
		$form .= '</form>' . chr(13);
		
		$html = '<div class="wrap">';
		$html .= '<h2>Dynamic Donation Export Template</h2><br>';
		$html .= '<p>Copy paste code of this file in your current theme directory. template-dynamic-donation-form.php, You can change its html as your need</p><br>';
		$html .= '<form method="post">';
		$html .= '<textarea rows="30" cols="80"><div id="donation-form">'.htmlentities($form).'</div></textarea><br><br>';
		$html .= '</form>';
		$html .= '</div>';
		print $html;
	}
	
	function get_payment_form() {
		$_ddf_paypal_standard = esc_attr(get_option('_ddf_paypal_standard'));
		
		
		$form = '';
		
		if($_ddf_paypal_standard == 1) {
			$form .= '<div class="form-control">' .chr(13);
			$form .= '	<div class="form-label">' .chr(13);
			$form .= '		Pay With Paypal' .chr(13);
			$form .= '	</div>' .chr(13);
			$form .= '	<div class="form-field">' .chr(13);
			$form .= '		<input type="submit" name="submit" value="Paypal">' .chr(13);
			$form .= '	</div>' .chr(13);
			$form .= '</div>' .chr(13);
		}
		
		
		
		
		return $form;
		
	}
	
	function register_mysettings() {
		//register our settings
		
		register_setting( 'ddf-settings-group', '_ddf_paypal_sandbox' );
		register_setting( 'ddf-settings-group', '_ddf_thanks_page' );
		register_setting( 'ddf-settings-group', '_ddf_error_page' );
		
		register_setting( 'ddf-settings-group', '_ddf_paypal_standard' );
		register_setting( 'ddf-settings-group', '_ddf_paypal_webisite_pro' );
		register_setting( 'ddf-settings-group', '_ddf_paypal_payflow' );
		
		register_setting( 'ddf-settings-group', '_ddf_paypal_email' );
		register_setting( 'ddf-settings-group', '_ddf_pro_api_username' );
		register_setting( 'ddf-settings-group', '_ddf_pro_api_password' );
		register_setting( 'ddf-settings-group', '_ddf_pro_api_singnature' );
		
		register_setting( 'ddf-settings-group', '_ddf_payflow_partner' );
		register_setting( 'ddf-settings-group', '_ddf_payflow_vendor' );
		register_setting( 'ddf-settings-group', '_ddf_payflow_machantid' );
		register_setting( 'ddf-settings-group', '_ddf_payflow_password' );
		
		register_setting( 'ddf-settings-group', '_ddf_default_geteway' );
		register_setting( 'ddf-settings-group', '_ddf_default_currency' );
		
	}
	
	function payment_settings() {
		?>
		<div class="wrap">
		<h2>Dynamic Donation Payment Settings</h2>
		
		<form method="post" action="options.php">
			<?php settings_fields( 'ddf-settings-group' ); ?>
			<?php do_settings_sections( 'ddf-settings-group' ); ?>
			<table class="form-table">
				<tr valign="top">
				<th scope="row">Payment Method</th>
				<td>
					<?php $ddf_paypal_standard = esc_attr( get_option('_ddf_paypal_standard','1') ); ?>
					
					<input type="checkbox" name="_ddf_paypal_standard" value="1" <?php print ($ddf_paypal_standard == 1) ? 'checked' : '';?>>&nbsp;Paypal
				</td>
				</tr>
				
				<tr valign="top">
				<th scope="row" colspan="2"><u>Payment</u></th>
				</tr>
				 
				<tr valign="top">
				<th scope="row">Transaction Type</th>
				<td>
					<select name="_ddf_paypal_sandbox" id="_ddf_paypal_sandbox">
						<option value="test" <?php print (get_option('_ddf_paypal_sandbox','test')) == 'test' ? 'selected' : '';?> >Test</option>
						<option value="live" <?php print (get_option('_ddf_paypal_sandbox','')) == 'live' ? 'selected' : '';?>>live</option>
					</select>
				</td>
				</tr>
				
				
				<tr valign="top">
				<th scope="row">Thanks You Page</th>
				<td><input type="text" name="_ddf_thanks_page" value="<?php echo esc_attr( get_option('_ddf_thanks_page','donation-thanks') ); ?>" /></td>
				</tr>
				 
				<tr valign="top">
				<th scope="row">Error Page</th>
				<td><input type="text" name="_ddf_error_page" value="<?php echo esc_attr( get_option('_ddf_error_page','donation-error') ); ?>" /></td>
				</tr>
				 
				<tr valign="top">
				<th scope="row" colspan="2"><u>Paypal Standard Payment</u></th>
				</tr>
				 
				<tr valign="top">
				<th scope="row">Paypal Email</th>
				<td><input type="text" name="_ddf_paypal_email" value="<?php echo esc_attr( get_option('_ddf_paypal_email','testseller@arcgate.com') ); ?>" /></td>
				</tr>
				
				
				<tr valign="top">
				<th scope="row" colspan="2"><u>Paypal Webisite Pro Settings</u></th>
				</tr>
				
				<tr valign="top">
				<th scope="row"><a href="http://www.scriptut.com/wordpress/wordpress-donation-form-plugin/" target="new">Premium Version</a></th>
				</tr>
				
				
				
				<tr valign="top">
				<th scope="row" colspan="2"><u>Payflow Pro Settings</u></th>
				</tr>
				
				<tr valign="top">
				<th scope="row"><a href="http://www.scriptut.com/wordpress/wordpress-donation-form-plugin/" target="new">Premium Version</a></th>
				</tr>
				
				
				
				<tr valign="top">
				<th scope="row" colspan="2"><u>Payflow Pro Settings</u></th>
				</tr>
				
				<tr valign="top">
				<th scope="row">Default Gateway</th>
				<td>
					<select name="_ddf_default_geteway">
						<option value="1" <?php print (get_option('_ddf_default_geteway') ==1) ? 'selected':''; ?>>Paypal Standard</option>
					</select>
				</td>
				</tr>
				<tr valign="top">
				<th scope="row">Currency</th>
				<td><input type="text" name="_ddf_default_currency" value="<?php echo esc_attr( get_option('_ddf_default_currency','USD') ); ?>" /></td>
				</tr>
				
			</table>
			
			<?php submit_button(); ?>
		
		</form>
		</div>
		<?php } 

	function ddf_field_edit() {
		global $wpdb;
		$html = $this->load_field_list(true);
		print $html;
		die(0);
	}
	
	function ddf_field_delete() {
		global $wpdb;
		$id = $_POST['id'];
		
		$where = array();
		$where['id'] = $id;
		$where_format = array('%d');
		
		$wpdb->delete( $this->field_table, $where, $where_format = null );
		$html = $this->load_field_list();
		print $html;
		die(0);
	}
	
	function ddf_field_new() {
		global $wpdb;
		$id = $_POST['id'];
		$name = strtolower(trim($_POST['name']));
		$fieldlabel = trim($_POST['fieldlabel']);
		$fieldtype = trim($_POST['fieldtype']);
		$field_options = trim($_POST['field_options']);
		
		$err = 0;
		$msg = array();
		$name = str_replace(" ","_",$name);
		$name = preg_replace('/\s.+/', '_', $name);
		
		if($name == '' && $id == 0) {
			$err = 1;
			$msg[] = "Please enter field name";
		}
		
		if($fieldlabel == '') {
			$err = 1;
			$msg[] = "Please enter field label";
		}
		
		$total_field = $wpdb->get_var( $wpdb->prepare("SELECT count(*)  as total FROM " . $this->field_table ." WHERE field_name = %s", $name));
		if($total_field > 0) {
			$err = 1;
			$msg[] = "Duplicate field name";
		}
		
		if($err == 0) {
			$table = $this->field_table;
			
			
			$format = array('%s', '%s' ,'%s');
			$where_format = array('%d');
			if($id > 0) {
				$data = array();
				$data['field_type'] = $fieldtype;
				$data['field_label'] = $fieldlabel;
				$data['field_options'] = $field_options;
				
				$where = array();
				$where['id'] = $id;
				
				$wpdb->update( $table, $data, $where, $format , $where_format );	
			}else {
				$data = array();
				$data['field_name'] = $name;
				$data['field_type'] = $fieldtype;
				$data['field_label'] = $fieldlabel;
				$data['field_options'] = $field_options;
				
				$wpdb->insert( $table, $data, $format );	
			}
			
			
			
		}
		
		$html = '';
		$html .= implode("<br>",$msg);
		$html .= $this->load_field_list();
		print $html;
		die(0);
		
	}
	
	function create_content_type() {
		$labels = array(
			'name'               => _x( 'Donations', 'post type general name', 'your-plugin-textdomain' ),
			'singular_name'      => _x( 'Donation', 'post type singular name', 'your-plugin-textdomain' ),
			'menu_name'          => _x( 'Donations', 'admin menu', 'your-plugin-textdomain' ),
			'name_admin_bar'     => _x( 'Donation', 'add new on admin bar', 'your-plugin-textdomain' ),
			'add_new'            => _x( 'Add New', 'book', 'your-plugin-textdomain' ),
			'add_new_item'       => __( 'Add New Donation', 'your-plugin-textdomain' ),
			'new_item'           => __( 'New Donation', 'your-plugin-textdomain' ),
			'edit_item'          => __( 'Edit Donation', 'your-plugin-textdomain' ),
			'view_item'          => __( 'View Donation', 'your-plugin-textdomain' ),
			'all_items'          => __( 'All Donations', 'your-plugin-textdomain' ),
			'search_items'       => __( 'Search Donations', 'your-plugin-textdomain' ),
			'parent_item_colon'  => __( 'Parent Donations:', 'your-plugin-textdomain' ),
			'not_found'          => __( 'No donations found.', 'your-plugin-textdomain' ),
			'not_found_in_trash' => __( 'No donations found in Trash.', 'your-plugin-textdomain' )
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'book' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title')
		);

		register_post_type( 'donation-form', $args );
	}
	
	
	function admin_load_js() {
		$url = plugins_url('dynamic-donation') . '/wp-dynamic-donation.js';
		print '<script src="'.$url.'"></script>';
	}
	
	
	
	function load_field_list($edit = false) {
		global $wpdb;
		$html = '';
		$html .= '<div class="wrap">';
		$html .= '<h2>Dynamic Donation Manage Fields</h2>';
		
		$fields_result = $wpdb->get_results( "SELECT * FROM " .$this->field_table." order by id");
		
		$html .= $this->field_form($edit);
		
		$html .= '<div style="border:1px solid #ccc;width:700px;">';
		$html .= '<form method="post"><table cellspacing="0" cellpadding="0" border="0" width="100%"  class="form-table">';
		$html .= '<tr>';
		$html .= '<td><b>';
		$html .= 'Field Name';
		$html .= '</td>';
		$html .= '<td><b>';
		$html .= 'Field Type';
		$html .= '</td><b>';
		$html .= '<td><b>';
		$html .= 'Field Label';
		$html .= '</td>';
		$html .= '<td><b>';
		$html .= 'Enabled';
		$html .= '</td>';
		$html .= '<td>&nbsp;</td>';
		$html .= '<td>&nbsp;</td>';
		$html .= '</tr>';
		
		for($i =0; $i< count($fields_result); ++$i) {
			
			$html .= '<tr>';
			$html .= '<td>';
			$html .= $fields_result[$i]->field_name;
			$html .= '</td>';
			$html .= '<td>';
			$html .= $fields_result[$i]->field_type;
			$html .= '</td>';
			$html .= '<td>';
			$html .= $fields_result[$i]->field_label;
			$html .= '</td>';
			$html .= '<td>';
			$checked = '';
			if($fields_result[$i]->enable == 1) {
			$checked = 'checked';	
			}
			$html .= '<input type="checkbox" value="1" onclick="ddf_field_status(\''.$fields_result[$i]->id.'\');" name="field_enable['.$fields_result[$i]->id.'][]" '.$checked.'>';
			$html .= '</td>';
			$html .= '<td><a href="javascript:void(0);" onclick="_ddf_edit(\''.$fields_result[$i]->id.'\');">Edit</a></td>';
			$html .= '<td><a href="javascript:void(0);" onclick="_ddf_delete(\''.$fields_result[$i]->id.'\');">Delete</a></td>';
			$html .= '</tr>';
		}
		$html .= '</table>';
		$html .= '</form></div>';
			
		$html .= '<script>
		 function ddf_field_status(id) {
			jQuery.post(\'admin-ajax.php\', {id:id,action:\'ddf_field_status\'},function(data) {
				jQuery("#field_list").html(data);
			});
		};';
		$html .= '</script></div>';
		
		return $html;
	}
	
	function field_form($edit = false) {
		global $wpdb;
		$fieldname = '';
		$fieldlabel = '';
		$fieldtype = '';
		$field_options = '';
		$id = 0;
		
		if($edit == true) {
			$id = $_POST['id'];
			$row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM " .$this->field_table." WHERE id = %d", $id));
			$fieldname = $row->field_name;
			$fieldlabel = $row->field_label;
			$fieldtype = $row->field_type;
			$field_options = $row->field_options;
		}
		
		
		
		$html .= '<div style="border:1px solid #ccc;width:700px;margin-bottom:30px;">';
		$html .= '<div style="padding-left:20px;padding-top:10px;"><b>Add new Field</b></div>';
		$html .= '<form id="ddf_add_field_form"><input type="hidden" id="id" value="'.$id.'"><table cellspacing="0" cellpadding="0"  class="form-table">';
		$html .= '<tr>';
		$html .= '<td>';
		$html .= '<label>Enter field machin name:</label>&nbsp;	';
		if($id > 0) {
			$html .= $fieldname;
		}else {
			$html .= '<input type="text" name="fieldname" id="fieldname" size="20" value="'.$fieldname.'" placeholder="Enter field machine name, only latters and underscore (_)">';
		}
		
		$html .= '<br><small>Only letters and underscore are allowed </small>';
		$html .= '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td>';
		$html .= '<label>Enter field label:</label>&nbsp;	';
		$html .= '<input type="text" name="fieldlabel"  value="'.$fieldlabel.'" id="fieldlabel" size="20" placeholder="Enter field Label">';
		$html .= '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td>';
		$html .= '<label>Select Field Type:</label>&nbsp;	';
		$html .= '<select type="select" name="fieldtype" id="fieldtype">';
		$fields_types_opt = array('text' => 'Text','select' => 'Select', 'checkbox' => 'Checkbox','textarea' => 'Textarea');
		foreach($fields_types_opt as $fk => $fv) {
			if($fk == $fieldtype) {
				$html .= '<option value="'.$fk.'" selected>'.$fv.'</option>';
			}else {
				$html .= '<option value="'.$fk.'" >'.$fv.'</option>';		
			}
			
		}

		$html .= '</select>';
		$html .= '</td>';
		$html .= '</tr>';
		
		
		$html .= '<tr>';
		$html .= '<td>';
		$html .= '<label>Enter field Option (only for select|checkbox|radio):</label>&nbsp;	';
		$html .= '<textarea name="field_options" id="field_options" rows="3" cols="40">'.$field_options.'</textarea>';
		$html .= '<br><small>Enter values like (M|Male,F|Female)</small>';
		$html .= '</td>';
		$html .= '</tr>';
		
		
		$html .= '<tr>';
		$html .= '<td>';
		$html .= '<button class="button-primary" onclick="_ddf_add_field();" type="button">Add</button>';
		$html .= '</td>';
		$html .= '</tr>';
		$html .= '</table></form>';
		$html .= '</div>';
		return $html;
	}
	
	function manage_fields() {
		
		global $wpdb;
		$html = '<div id="field_list">';
		$html .= $this->load_field_list();
		$html .= '</div>';
		print $html;
	}
	
	function ddf_field_status() {
		global $wpdb;
		$id = $_POST['id'];
		
		$row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM " .$this->field_table." WHERE id = %d", $id));
		if($row->enable == 1) {
			$data['enable'] = 0;
			$where['id'] = $id;
			$wpdb->update( $this->field_table, $data, $where); 
		}else {
			$data['enable'] = 1;
			$where['id'] = $id;
			$wpdb->update( $this->field_table, $data, $where); 
		}
		print $this->load_field_list();
		die(0);
	}
	
	function default_fields() {
		$default_fields = array(
			'_ddf_amt'
			,'_ddf_title'
			,'_ddf_first_name'
			,'_ddf_last_name'
			,'_ddf_mobile'
			,'_ddf_address1'
			,'_ddf_address2'
			,'_ddf_city'
			,'_ddf_state'
			,'_ddf_zip'
			,'_ddf_country'
		);
		
		
	}
	
	function ddf_install() {
		global $wpdb;
				$table = $wpdb->prefix . "ddf_fields";
		
		$sql = "CREATE TABLE  ".$table." (
				id INT NOT NULL AUTO_INCREMENT,
				field_name VARCHAR( 100 ) NULL ,
				field_type VARCHAR( 10 ) NULL ,
				field_label VARCHAR( 100 ) NULL ,
				enable INT( 1 ) NULL ,PRIMARY KEY (  `id` )
			) ENGINE = MYISAM ;";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		$sql = "INSERT INTO  ".$table." (field_name, field_type,field_label,enable) 
          VALUES ('amount',  'text', 'Amount', '1')
            ,('title',  'select', 'Title', '1')
            ,('first_name',  'text', 'First Name', '1')
            ,('last_name',  'text', 'Last Name', '1')
            ,('mobile',  'text', 'Mobile', '1')
            ,('address1',  'text', 'Address 1', '1')
            ,('address2',  'text', 'Address 2', '1')
            ,('city',  'text', 'City', '1')
            ,('state',  'text', 'State', '1')
            ,('zip',  'text', 'Zip', '1')
            ,('country',  'text', 'Country', '1')";
		dbDelta($sql);
		
	}	
	function ddf_uninstall() {		global $wpdb;
		global $ddf_db_version;
		$table_name = $wpdb->prefix . "ddf_fields";
		$sql = "DROP TABLE IF EXISTS $table_name";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}	}
new dynamic_donation();
